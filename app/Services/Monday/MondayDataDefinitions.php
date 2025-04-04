<?php

namespace App\Services\Monday;

class MondayDataDefinitions
{
    public static function getItemsData()
    {
        return [
            'BoardRelationValue' => '... on BoardRelationValue {
                display_value
                linked_item_ids
            }',
            'ButtonValue' => '... on ButtonValue {
                color
                text
            }',
            'CheckboxValue' => '... on CheckboxValue {
                checked
            }',
            'ColorPickerValue' => '... on ColorPickerValue {
                color
            }',
            'CountryValue' => '... on CountryValue {
                country {
                    code
                    name
                }
                value
            }',
            'CreationLogValue' => '... on CreationLogValue {
                created_at
                creator {
                    id
                    birthday
                    country_code
                    current_language
                    email
                    url
                    title
                    phone
                }
            }',
            'DateValue' => '... on DateValue {
                date
                time
                text
            }',
            'DependencyValue' => '... on DependencyValue {
                display_value
                text
            }',
            'DocValue' => '... on DocValue {
                file {
                    doc {
                        id
                        url
                        relative_url
                        name
                    }
                    creator {
                        id
                        email
                    }
                    url
                }
            }',
            'DropdownValue' => '... on DropdownValue {
                text
                values {
                    label
                }
            }',
            'EmailValue' => '... on EmailValue {
                email
                text
            }',
            'FileValue' => '... on FileValue {
                files {
                    __typename
                }
            }',
            'FormulaValue' => '... on FormulaValue {
                text
                value
            }',
            'GroupValue' => '... on GroupValue {
                group_id
            }',
            'HourValue' => '... on HourValue {
                hour
                minute
            }',
            'IntegrationValue' => '... on IntegrationValue {
                text
                entity_id
                issue_api_url
                issue_id
            }',
            'ItemIdValue' => '... on ItemIdValue {
                item_id
            }',
            'LastUpdatedValue' => '... on LastUpdatedValue {
                updated_at
                updater {
                    name
                    id
                    email
                }
            }',
            'LinkValue' => '... on LinkValue {
                url
                text
            }',
            'LocationValue' => '... on LocationValue {
                lat
                lng
                address
                city
                city_short
                country_short
                place_id
                street
                street_number
                street_number_short
                street_short
            }',
            'LongTextValue' => '... on LongTextValue {
                text
            }',
            'MirrorValue' => '... on MirrorValue {
                display_value
            }',
            'NumbersValue' => '... on NumbersValue {
                direction
                symbol
                number
            }',
            'PeopleValue' => '... on PeopleValue {
                persons_and_teams {
                    id
                    kind
                }
            }',
            'PersonValue' => '... on PersonValue {
                person_id
            }',
            'PhoneValue' => '... on PhoneValue {
                phone
                country_short_name
            }',
            'ProgressValue' => '... on ProgressValue {
                text
            }',
            'RatingValue' => '... on RatingValue {
                rating
            }',
            'StatusValue' => '... on StatusValue {
                label
                is_done
                index
            }',
            'SubtasksValue' => '... on SubtasksValue {
                subitems_ids
            }',
            'TagsValue' => '... on TagsValue {
                tag_ids
                tags {
                    id
                    name
                }
            }',
            'TeamValue' => '... on TeamValue {
                team_id
            }',
            'TextValue' => '... on TextValue {
                text
            }',
            'TimelineValue' => '... on TimelineValue {
                from
                to
                text
            }',
            'TimeTrackingValue' => '... on TimeTrackingValue {
                history {
                    started_user_id
                    ended_user_id
                    started_at
                    ended_at
                    manually_entered_end_date
                    manually_entered_end_time
                    manually_entered_start_date
                    manually_entered_start_time
                }
                running
                started_at
            }',
            'UnsupportedValue' => '... on UnsupportedValue {
                text
            }',
            'VoteValue' => '... on VoteValue {
                voters {
                    id
                    name
                    email
                    url
                }
                vote_count
            }',
            'WeekValue' => '... on WeekValue {
                start_date
                end_date
            }',
            'WorldClockValue' => '... on WorldClockValue {
                timezone
                text
            }'
        ];
    }
}
