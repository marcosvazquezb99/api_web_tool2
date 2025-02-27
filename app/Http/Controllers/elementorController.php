<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class elementorController extends Controller
{

    public function generatePageFromTemplate(Request $request)
    {
        $template = $request->input('template');
        $data = $request->input('variables');
        //open file $template
//        $template = file_get_contents($template);
        //get json
//        $json = json_decode($template, true);
//        $page = $request->input('page');
        $page = $this->processData($template, $data);
        return response()->json($page);


    }

    public function processData(mixed $template, array $variables = [], string $apiKey = null)
    {
        $apiKey = $apiKey ?? env('GPT_API');
        // Decodifica el JSON
        $data = $template;

        if (is_null($data)) {
            throw new Exception("JSON inválido");
        }

        $selections = []; // Para guardar las opciones seleccionadas por id
        $arrayKeys = ['editor', 'title', 'htmlCache', 'type', 'elements'];
        // Función recursiva para procesar el JSON
        $processJson = function (&$item) use (&$processJson, &$selections, $variables, $apiKey, $arrayKeys) {
            if (is_array($item)) {
                foreach ($item as $key => &$value) {
//                    dd($key);
                    if (is_null($value)) {
                        $value = "";
                    }

                    if (true) {

//                        dd($key);
                        if (is_array($value) || is_object($value)) {
                            $processJson($value);
                        } elseif (is_string($value) && $value !== "") {
                            // Reglas de reemplazo
                            // 1. Reemplazo de {{variable}}
                            $value = preg_replace_callback('/{{(.*?)}}/', function ($matches) use ($variables) {
                                $varName = trim($matches[1]);
                                return $variables[$varName] ?? $matches[0];
                            }, $value);

                            // 2. Selección aleatoria [[ opcion1 | opcion2 | opcion3 : id ]]
                            $value = preg_replace_callback('/\[\[(.*?)\s*:\s*(.*?)\]\]/', function ($matches) use (&$selections) {
                                $options = array_map('trim', explode('|', $matches[1]));
                                $id = trim($matches[2]);
                                $selected = $options[array_rand($options)];
                                $selections[$id] = $selected;
//                                dd($selected);
                                return $selected;
                            }, $value);


                            // 3. Llamadas a ChatGPT {{ promt: string ${id} }}
                            if (preg_match('/{{\s*prompt\s*:\s*(.*?)\s*}}/', $value, $matches)) {
                                $prompt = $matches[1];


                                // Reemplaza variables en el prompt
                                $prompt = preg_replace_callback('/\${(.*?)}/', function ($matches) use ($variables, $selections) {
                                    $varName = trim($matches[1]);

                                    return $variables[$varName] ?? $selections[$varName] ?? $matches[0];
                                }, $prompt);
//                                $value = preg_replace('/{{\s*prompt\s*:\s*(.*?)\s*}}/', $prompt, $value);
                                /*if (count($selections)>0){
                                    dd($value, $matches);
                                }*/
                                // Llama a ChatGPT
                                $response = Http::withHeaders([
                                    'Authorization' => 'Bearer ' . $apiKey,
                                ])->post('https://api.openai.com/v1/chat/completions', [
                                    'model' => 'gpt-4o-mini',
//                                    'prompt' => $prompt,
                                    'messages' => [
                                        [
                                            'role' => "system",
                                            'content' => "Copywriter experto en SEO para una empresa de diseño web"
                                        ],
                                        [
                                            'role' => "user",
                                            'content' => $prompt
                                        ]
                                    ],
                                    'max_tokens' => 400,
                                ]);

                                if ($response->ok()) {
                                    $message = $response->json()['choices'][0]['message']['content'] ?? '';
                                    $value = preg_replace('/{{\s*prompt\s*:\s*(.*?)\s*}}/', $message, $value);
//                                    dd($value, $response->json());
                                } else {
                                    throw new Exception("Error en la API de ChatGPT: " . $response->body());
                                }
                            }


                        }
                    }
                }
            }
        };

        // Procesa el JSON
        $processJson($data);
//        dd($data);
        // Devuelve el JSON modificado y las selecciones
        return [
            'modifiedJson' => $data,
            'selections' => $selections,
        ];
    }

}
