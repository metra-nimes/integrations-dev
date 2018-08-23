<?php

/**
 * MailChimp integration
 * @link http://developer.mailchimp.com/documentation/mailchimp/
 */

class Integration_Driver_MailChimp extends Integration_Driver implements Integration_Interface_ContactStorage {

    protected static $company_name    = 'Rocket Science Group LLC d/b/a MailChimp';
    protected static $company_address = '675 Ponce de Leon Ave NE, Suite 5000, Atlanta, GA 30308 USA';
    protected static $company_url     = 'https://mailchimp.com/';

    /**
     * Separator for the combination of the ids
     * Example: list_id. IDS_SEPARATOR .segment_id
     */
    const IDS_SEPARATOR = '_';

    /**
     *
     */
    const DEFAULT_LIMIT = 1000;

    /**
     * Get endpoint URL for API calls
     *
     * @var string API Endpoint
     * @return string
     */
    public function get_endpoint(): string
    {
        if ( ! preg_match('~\-([a-z0-9]{2,8})$~', $this->get_credentials('api_key', ''), $matches))
        {
            throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
        }

        return sprintf('https://%s.api.mailchimp.com/3.0', $matches[1]);
    }

    /**
     * Describes COF fieldset config to render a credentials form, so a user could connect integration account
     *
     * @param boolean $refresh
     * @return array
     */
    public function describe_credentials_fields($refresh = FALSE): array
    {
        return [
            'name' => [
                'title' => 'Account Name',
                'description' => 'It\'s an internal value, which can be helpful to identify a specific account in future.',
                'type' => 'text',
                'rules' => [
                    ['not_empty'],
                ],
            ],
            'api_key' => [
                'title' => 'Account API Key',
                'description' => '<a href="/docs/integrations/mailchimp/#step-2-get-your-mailchimp-api-key" target="_blank">Read where to obtain this code</a>',
                'type' => 'key',
                'rules' => [
                    ['not_empty'],
                    ['regex', [':value', '~\-[a-z0-9]{2,8}$~']],
                ],
            ],
            'submit' => [
                'title' => 'Connect with MailChimp',
                'action' => 'connect',
                'type' => 'submit',
            ],
        ];
    }

    /**
     * Fetch meta data by integration credentials
     *
     * @return self
     */
    public function fetch_meta()
    {
        $this->meta = [
            'lists'           => [],
            'groups'          => [], # Key: list_id + "_" + interest_category_id + "_" + interest_id
            'static_segments' => [], # Key: list_id + "_" + segment_id
            'merge_fields'    => [], # Key: list_id + "_" + merge_id
        ];

        # Get meta data
        $this->get_lists(TRUE);
        $this->get_groups(TRUE);
        $this->get_static_segments(TRUE);
        $this->get_merge_fields(TRUE);

        return $this;
    }

    /**
     * Get lists
     *
     * @param  boolean $force_fetch Prevent using cached version
     * @return array
     * @throws Integration_Exception
     */
    private function get_lists($force_fetch = FALSE): array
    {
        if( ! $force_fetch)
        {
            return $this->get_meta('lists', []);
        }

        # Lists
        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/#read-get_lists
        $res = Integration_Request::factory()
            ->method('GET')
            ->url($this->get_endpoint().'/lists')
            ->header('Content-Type', 'application/json')
            ->data([
                'fields' => 'lists.id,lists.name',
                'count'  => self::DEFAULT_LIMIT, // Cannot use some "no limit" value, using extra-big value instead
            ])
            ->http_basic_auth('user', $this->get_credentials('api_key', ''))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            switch ($res->code)
            {
                case 401:
                case 403:
                    throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                    break;
                case 404:
                    throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                    break;
                case 409:
                    throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
                    break;
                case 500:
                    throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                    break;
                default:
                    throw new Integration_Exception(INT_E_WRONG_REQUEST);
                    break;
            }
        }

        foreach ($res->get('lists', []) as $list)
        {
            Arr::set_path($this->meta, 'lists.'.Arr::get($list, 'id'), Arr::get($list, 'name', ''));
        }

        unset($res);
        return $this->get_meta('lists', []);
    }

    /**
     * Get groups
     *
     * @param  boolean $force_fetch Prevent using cached version
     * @return array
     * @throws Integration_Exception
     */
    private function get_groups($force_fetch = FALSE): array
    {
        if( ! $force_fetch)
        {
            return $this->get_meta('groups', []);
        }

        $lists = (array) $this->get_meta('lists', []);

        # list_id => (array) cat_ids
        $interest_cats = [];

        # Interest Categories
        foreach ($lists as $list_id => $list_name)
        {
            # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/interest-categories/#read-get_lists_list_id_interest_categories
            $res = Integration_Request::factory()
                ->method('GET')
                ->url($this->get_endpoint().'/lists/'.$list_id.'/interest-categories')
                ->header('Content-Type', 'application/json')
                ->data([
                    'fields' => 'categories.id',
                    'count'  => self::DEFAULT_LIMIT, // Cannot use some "no limit" value, using extra-big value instead
                ])
                ->http_basic_auth('user', $this->get_credentials('api_key', ''))
                ->log_to($this->requests_log)
                ->execute();

            if ( ! $res->is_successful())
            {
                switch ($res->code)
                {
                    case 401:
                    case 403:
                        throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                        break;
                    case 404:
                        throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                        break;
                    case 409:
                        throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
                        break;
                    case 500:
                        throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                        break;
                    default:
                        throw new Integration_Exception(INT_E_WRONG_REQUEST);
                        break;
                }
            }

            foreach ($res->get('categories', []) as $cat)
            {
                $interest_cats[$list_id][] = Arr::get($cat, 'id', '');
            }
        }

        # Interests
        foreach ($interest_cats as $list_id => $cats)
        {
            foreach ($cats as $cat_id)
            {
                # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/interest-categories/interests/#read-get_lists_list_id_interest_categories_interest_category_id_interests
                $res = Integration_Request::factory()
                    ->method('GET')
                    ->url($this->get_endpoint().'/lists/'.$list_id.'/interest-categories/'.$cat_id.'/interests')
                    ->header('Content-Type', 'application/json')
                    ->data([
                        'fields' => 'interests.id,interests.name',
                        'count'  => self::DEFAULT_LIMIT, // Cannot use some "no limit" value, using extra-big value instead
                    ])
                    ->http_basic_auth('user', $this->get_credentials('api_key', ''))
                    ->log_to($this->requests_log)
                    ->execute();

                if ( ! $res->is_successful())
                {
                    switch ($res->code)
                    {
                        case 401:
                        case 403:
                            throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                            break;
                        case 404:
                            throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                            break;
                        case 409:
                            throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
                            break;
                        case 500:
                            throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                            break;
                        default:
                            throw new Integration_Exception(INT_E_WRONG_REQUEST);
                            break;
                    }
                }

                foreach ($res->get('interests', []) as $interes)
                {
                    // list_id + "_" + interest_category_id + "_" + interest_id
                    $format_id = implode(self::IDS_SEPARATOR, [
                        $list_id,
                        $cat_id,
                        Arr::get($interes, 'id')
                    ]);
                    Arr::set_path($this->meta, 'groups.'.$format_id, Arr::get($interes, 'name', ''));
                }
            }
        }

        unset($res, $lists, $interest_cats);
        return $this->get_meta('groups', []);
    }

    /**
     * Get static segments
     *
     * @param  boolean $force_fetch Prevent using cached version
     * @return array
     * @throws Integration_Exception
     */
    private function get_static_segments($force_fetch = FALSE): array
    {
        if ( ! $force_fetch)
        {
            return $this->get_meta('static_segments', []);
        }

        $lists = (array) $this->get_meta('lists', []);

        # Segments
        foreach ($lists as $list_id => $list_name)
        {
            # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/segments/#read-get_lists_list_id_segments
            $res = Integration_Request::factory()
                ->method('GET')
                ->url($this->get_endpoint().'/lists/'.$list_id.'/segments')
                ->header('Content-Type', 'application/json')
                ->data([
                    'fields' => 'segments.id,segments.name,segments.type',
                    'count'  => self::DEFAULT_LIMIT, // Cannot use some "no limit" value, using extra-big value instead
                ])
                ->http_basic_auth('user', $this->get_credentials('api_key', ''))
                ->log_to($this->requests_log)
                ->execute();

            if ( ! $res->is_successful())
            {
                switch ($res->code)
                {
                    case 401:
                    case 403:
                        throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                        break;
                    case 404:
                        throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                        break;
                    case 409:
                        throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
                        break;
                    case 500:
                        throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                        break;
                    default:
                        throw new Integration_Exception(INT_E_WRONG_REQUEST);
                        break;
                }
            }

            foreach ($res->get('segments', []) as $segment)
            {
                # Types: saved, static, fuzzy
                if (Arr::get($segment, 'type') == 'static')
                {
                    // list_id + "_" + segment_id
                    $format_id = implode(
                        self::IDS_SEPARATOR,
                        [
                            $list_id,
                            Arr::get($segment, 'id')
                        ]
                    );
                    Arr::set_path($this->meta, 'static_segments.'.$format_id, Arr::get($segment, 'name', ''));
                }
            }
        }

        unset($res, $lists);
        return $this->get_meta('static_segments', []);
    }

    /**
     * Get merge fields
     *
     * @param boolean $force_fetch Prevent using cached version
     * @param string|bool $list_id
     * @return array
     * @throws Integration_Exception
     */
    private function get_merge_fields($force_fetch = FALSE, $list_id = FALSE)
    {
        if ( ! $force_fetch)
        {
            return $this->get_meta('merge_fields', []);
        }

        $lists = (array) $this->get_meta('lists', []);
        $lists = array_keys($lists);

        if ($list_id AND in_array($list_id, $lists))
        {
            $lists = [$list_id];
        }

        # Merge Fields
        foreach ($lists as $list_id)
        {
            # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/merge-fields/#read-get_lists_list_id_merge_fields
            $res = Integration_Request::factory()
                ->method('GET')
                ->url($this->get_endpoint().'/lists/'.$list_id.'/merge-fields')
                ->header('Content-Type', 'application/json')
                ->data([
                    //'fields' => 'merge_fields.merge_id,merge_fields.name',
                    'count'  => self::DEFAULT_LIMIT, // Cannot use some "no limit" value, using extra-big value instead
                ])
                ->http_basic_auth('user', $this->get_credentials('api_key', ''))
                ->log_to($this->requests_log)
                ->execute();

            if ( ! $res->is_successful())
            {
                switch ($res->code)
                {
                    case 401:
                    case 403:
                        throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                        break;
                    case 404:
                        throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                        break;
                    case 409:
                        throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
                        break;
                    case 500:
                        throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                        break;
                    default:
                        throw new Integration_Exception(INT_E_WRONG_REQUEST);
                        break;
                }
            }

            foreach ($res->get('merge_fields', []) as $field)
            {
                $this->meta['merge_fields'][$list_id][Arr::get($field, 'merge_id')] = [
                    'tag' => Arr::get($field, 'tag'),
                    'name' => Arr::get($field, 'name')
                ];
            }
        }

        unset($res, $lists);
        return $this->get_meta('merge_fields', []);
    }

    /**
     * Get tags names
     *
     * @param  boolean $force_fetch Prevent using cached version
     * @return array
     */
    private function get_tags_names($list_id = NULL, $force_fetch = FALSE): array
    {
        $merge_fields = $this->get_merge_fields($force_fetch, $list_id);
        $merge_fields = Arr::get($merge_fields, $list_id, []);

        return array_combine(
            Arr::pluck($merge_fields, 'tag'),
            Arr::pluck($merge_fields, 'name')
        );
    }

    /**
     * @var int For how long we wait for optin confirmation before we consider that we don't have this subscriber?
     */
    protected $pending_timeout = 10 * Date::MINUTE;

    /**
     * Get person by email
     *
     * @param string $email
     * @param bool $need_translate Prevent using cached version
     * @return array|NULL Person data or NULL, if person not found
     * @throws Integration_Exception
     */
    public function get_subscriber($email, $need_translate = TRUE)
    {
        # http://developer.mailchimp.com/documentation/mailchimp/reference/search-members/
         $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key'))
            ->method('GET')
            ->url($this->get_endpoint().'/search-members?query='.mb_strtolower($email))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            switch ($res->code)
            {
                case 401:
                case 403:
                    throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                    break;
                case 500:
                    throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                    break;
                default:
                    break;
            }
        }

        $data = Arr::path($res->data, 'exact_matches.members.0', []);

        # Considering a unconfirmed person subscribed if it was added recently
        $status        = Arr::get($data, 'status');
        $last_changed  = Arr::get($data, 'last_changed');
        $is_subscribed = ($status == 'subscribed') OR ($status == 'pending' AND (time() - strtotime($last_changed)) < $this->pending_timeout);

        if ($is_subscribed)
        {
            return $need_translate ? $this->translate_int_data_to_subscriber_data($data) : $data;
        }
        unset($res, $status, $last_changed, $is_subscribed, $data);

        return NULL;
    }

    /**
     * Add or update a list member
     *
     * @param  string  $email
     * @param  array $subscriber_data
     * @param  boolean $update
     * @return array
     * @throws Integration_Exception
     */
    private function put_subscriber(string $email, array $subscriber_data = [], $update = FALSE): array
    {
        $data = Arr::merge(
            $this->translate_subscriber_data_to_int_data($subscriber_data, $update),
            [
                'email_address' => $email
            ]
        );

        # New subscriber
        if ( ! $update)
        {
            $data = Arr::merge(
                $data,
                [
                    'ip_signup' => Request::$client_ip,
                    # Statuses: subscribed, unsubscribed, cleaned, pending
                    'status'    => 'subscribed',
                ]
            );
        }

        # Subscriber params
        foreach (['status_if_new', 'interests', 'vip'] as $param)
        {
            if (Arr::get($subscriber_data, $param, NULL) !== NULL)
            {
                $data[$param] = Arr::get($subscriber_data, $param);
            }
        }

        $list_id = Arr::get($subscriber_data, 'list_id');

        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/#edit-patch_lists_list_id_members_subscriber_hash
        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/#edit-put_lists_list_id_members_subscriber_hash
        $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key', ''))
            ->method($update ? 'PATCH' : 'PUT')
            // MailChimp requires JSON-encoded post data
            ->header('Content-Type', 'application/json')
            ->url($this->get_endpoint().'/lists/'.$list_id.'/members/'.md5(mb_strtolower($email)))
            ->data($data)
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            if ($res->code == 401 OR $res->code == 403)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
            }
            elseif ($res->code == 400)
            {
                // Check for banned e-mail
                if (stripos($res->body, 'signed up to a lot of lists') !== FALSE)
                {
                    throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail not allowed for more signups now');
                }
                // Check for duplicate e-mail
                elseif (stripos($res->body, 'already a list member') !== FALSE)
                {
                    throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email', 'User is already a list member');
                }
                elseif (stripos($res->body, 'was permanently deleted and cannot be') !== FALSE)
                {
                    throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Already unsubscribed');
                }
                // Check for banned e-mail
                elseif (stripos($res->body, 'looks fake or invalid') !== FALSE )
                {
                    throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail looks fake or invalid');
                }
                // Check for not valid e-mail
                elseif (stripos($res->body, 'The resource submitted could not be validated') !== FALSE)
                {
                    throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail looks fake or invalid');
                }
                // Check for subscribed e-mail
                elseif (stripos($res->body, 'is in a compliance state') !== FALSE)
                {
                    //throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Member In Compliance State');
                    return TRUE;
                }
                elseif (stripos($res->body, 'Your merge fields were invalid.') !== FALSE)
                {
                    $err = json_decode($res->body, TRUE);
                    if ( ! empty($err['errors']))
                    {
                        throw new Integration_Exception(INT_E_WRONG_PARAMS, 'email', $err['errors'][0]['field'].' '.$err['errors'][0]['message']);
                    }
                }
                else
                {
                    throw new Integration_Exception(INT_E_WRONG_REQUEST);
                }
            }
            else
            {
                switch($res->code)
                {
                    case 401:
                    case 403:
                        throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                        break;
                    case 404;
                        throw new Integration_Exception(INT_E_WRONG_PARAMS);
                        break;
                    case 500:
                        throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                        break;
                    case 508:
                        throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                        break;
                    default:
                        throw new Integration_Exception(INT_E_WRONG_REQUEST);
                        break;
                }
            }
        }
        elseif ( ! empty($this->_errors_create_tag))
        {
            $this->_errors_create_tag = array_unique($this->_errors_create_tag);
            $key = key($this->_errors_create_tag);

            throw new Integration_Exception(INT_E_WRONG_PARAMS, $key, Arr::get($this->_errors_create_tag, $key));
        }

        // Verifying email
        if (mb_strtolower($res->get('email_address')) !== mb_strtolower($email))
        {
            throw new Integration_Exception(INT_E_EMAIL_NOT_VERIFIED);
        }

        // Verifying the data
        foreach (Arr::get($data, 'merge_fields', []) as $f_tag => $f_value)
        {
            $f_value = trim(strip_tags($f_value));
            if (trim(strip_tags($res->path('merge_fields.'.$f_tag))) !== $f_value)
            {
                throw new Integration_Exception(INT_E_DATA_NOT_VERIFIED, $f_tag);
            }
        }

        unset($list_id);
        return $res->data;
    }

    /**
     * Auxiliary variable for storing errors
     * @var array
     */
    protected $_errors_create_tag = [];

    /**
     * Create merge field
     *
     * @param string $list_id
     * @param string $tag
     * @param string $name
     * @return string|bool
     */
    private function create_merge_field(string $list_id, string $tag, string $name)
    {
        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/merge-fields/#create-post_lists_list_id_merge_fields
        $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key'))
            ->method('POST')
            // MailChimp requires JSON-encoded post data
            ->header('Content-Type', 'application/json')
            ->url($this->get_endpoint().'/lists/'.$list_id.'/merge-fields')
            ->data(array(
                'tag'  => $tag,
                'name' => $name,
                'type' => 'text',
            ))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            if ($res->code == 400 AND stripos($res->body, 'Merge Max Limit Exceeded') !== FALSE)
            {
                $body = json_decode($res->body, TRUE);
                $this->_errors_create_tag[$name] = 'Integration error while create field: '.Arr::get($body, 'detail', 'Did not create');
                return FALSE;
            }
            elseif ($res->code == 404 AND stripos($res->body, 'The requested resource could not be found') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list', 'List not found');
            }
            elseif (stripos($res->body, 'already exists for this list') !== FALSE)
            {
                return $tag;
            }
            throw new Integration_Exception(INT_E_WRONG_REQUEST);
        }

        $merge_id = $res->get('merge_id');

        # Adding field to meta
        Arr::set_path(
            $this->meta, 'merge_fields.'.$list_id.'.'.$merge_id,
            [
                'tag'  => $tag,
                'name' => $name,
            ]
        );

        unset($res, $list_id, $merge_id);
        return $tag;
    }

    /**
     * @var array Merge fields tag names for Convertful person fields
     */
    protected $standard_merge_fields = [
        'first_name' => 'FNAME',
        'last_name'  => 'LNAME',
        'name'       => 'NAME',
        'phone'      => 'PHONE',
        'company'    => 'COMPANY',
        'site'       => 'SITE',
    ];

    /**
     * Translate person data from standard convertful to integration format
     *
     * @param array $subscriber_data Person data in standard convertful format
     * @param bool $create_missing_fields
     * @return array Integration-specified person data format
     * @throws Integration_Exception
     */
    public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE): array
    {
        $list_id = Arr::get($subscriber_data, 'list_id');
        $subscriber_meta   = Arr::get($subscriber_data, 'meta', []);

        # Deleting auxiliary parameters
        foreach (['status', 'status_if_new', 'meta', 'list_id', 'interests', 'vip', 'note'] as $key)
        {
            unset($subscriber_data[$key]);
        }

        $force_fetch_tags  = FALSE;
        $tags_names        = $this->get_tags_names($list_id, $force_fetch_tags);
        $mf_data           = [];

        # Custom person fields first (so they won't overwrite standard fields)
        if ( ! empty($subscriber_meta))
        {
            # Reserved tags to avoid https://kb.mailchimp.com/merge-tags/reserved-field-names-to-avoid
            $reserved_tags = [
                'INTERESTS', 'UNSUB', 'FORWARD', 'REWARDS', 'ARCHIVE',
                'USER_URL', 'DATE', 'EMAIL', 'EMAIL_TYPE', 'TO'
            ];

            foreach ($subscriber_meta as $mf_name => $mf_value)
            {
                // Trying to find existing relevant merge field by its title
                $tag = array_search($mf_name, $tags_names, TRUE);

                if ( ! $tag)
                {
                    // Generating field tag
                    $tag      = mb_substr(preg_replace('~[^A-Z\d\_]+~', '', mb_strtoupper($mf_name)), 0, 10);
                    $tag_base = mb_substr($tag, 0, 9);

                    if (empty($tag))
                    {
                        // Non-ascii symbols case
                        $tag = ($tag_base = 'FIELD').($tag_index = 1);
                    }

                    while (isset($tags_names[$tag]) OR in_array($tag, $reserved_tags) OR in_array($tag, $this->standard_merge_fields))
                    {
                        $tag_index = isset($tag_index) ? ($tag_index + 1) : 1;
                        if ($tag_index > 9)
                        {
                            // Too much tries ... just skipping the field
                            continue 2;
                        }
                        $tag = $tag_base.$tag_index;
                    }
                    unset($tag_index);

                    if ($create_missing_fields)
                    {
                        # Reload tags
                        if ( ! $force_fetch_tags)
                        {
                            $force_fetch_tags = TRUE;
                            $tags_names       = $this->get_tags_names($list_id, $force_fetch_tags);
                        }

                        if ( ! array_key_exists($tag_base, $tags_names))
                        {
                            // Creating new merge field
                            $tag = $this->create_merge_field($list_id, $tag, $mf_name);
                        }

                        if ($tag)
                        {
                            // Updating $tags_names so the added tag presents there
                            $tags_names[$tag] = $mf_name;
                        }
                    }
                }

                if ($tag)
                {
                    $mf_data[$tag] = $mf_value;
                }
            }
        }

        # Standard subscriber fields
        if ( ! empty($subscriber_data))
        {
            foreach ($subscriber_data as $mf_type => $mf_value)
            {
                $tag = Arr::get($this->standard_merge_fields, $mf_type, mb_strtoupper($mf_type));

                if( ! isset($tags_names[$tag]))
                {
                    # Human-readable type format
                    $mf_name = Inflector::humanize(ucfirst($mf_type));
                    if ($create_missing_fields)
                    {
                        if ( ! $force_fetch_tags)
                        {
                            $force_fetch_tags = TRUE;
                            $tags_names       = $this->get_tags_names($list_id, $force_fetch_tags);
                        }

                        if ( ! array_key_exists($tag, $tags_names))
                        {
                            // Creating new merge field
                            $tag = $this->create_merge_field($list_id, $tag, $mf_name);
                        }

                        if ($tag)
                        {
                            // Updating $tags_names so the added tag presents there
                            $tags_names[$tag] = $mf_name;
                        }
                    }
                }

                if ($tag)
                {
                    $mf_data[$tag] = $mf_value;
                }
            }
        }

        # Trying to use standard FNAME / LNAME when name is defined
        if (isset($mf_data['NAME']) AND ! empty($mf_data['NAME']) AND ! isset($mf_data['FNAME']) AND ! isset($mf_data['LNAME']))
        {
            $tags_names = $this->get_tags_names($list_id, $force_fetch_tags);
            $name       = explode(' ', $mf_data['NAME'], 2);
            $tag        = "FNAME";

            if ($create_missing_fields AND ! isset($tags_names['FNAME']))
            {
                if ( ! $force_fetch_tags)
                {
                    $force_fetch_tags = TRUE;
                    $tags_names       = $this->get_tags_names($list_id, $force_fetch_tags);
                }

                if ( ! array_key_exists($tag, $tags_names))
                {
                    $tag = $this->create_merge_field($list_id, $tag, 'First name');
                }
            }

            if ($tag)
            {
                $mf_data['FNAME'] = $name[0];
            }

            if (isset($name[1]))
            {
                $tag = "LNAME";
                if ($create_missing_fields AND ! isset($tags_names['LNAME']))
                {
                    if ( ! $force_fetch_tags)
                    {
                        $force_fetch_tags = TRUE;
                        $tags_names       = $this->get_tags_names($list_id, $force_fetch_tags);
                    }

                    if ( ! array_key_exists($tag, $tags_names))
                    {
                        $tag = $this->create_merge_field($list_id, $tag, 'Last name');
                    }
                }

                if ($tag)
                {
                    $mf_data['LNAME'] = $name[1];
                }
            }
        }

        unset($force_fetch_tags, $tags_names, $subscriber_meta, $list_id);
        return empty($mf_data) ? [] : ['merge_fields' => $mf_data];
    }

    /**
     * Translate person data from integration to standard convertful format
     *
     * @param array $int_data Person data in integration format
     * @return array Person data in standard convertful format
     */
    public function translate_int_data_to_subscriber_data(array $int_data): array
    {
        $data       = [];
        $list_id    = Arr::get($int_data, 'list_id');
        $tags_names = $this->get_tags_names($list_id);

        foreach (Arr::get($int_data, 'merge_fields', []) as $prop => $value)
        {
            if (empty($value))
            {
                continue;
            }

            if ($f_type = array_search($prop, $this->standard_merge_fields, TRUE))
            {
                # Standard type
                $data[$f_type] = trim($value);
                continue;
            }
            else
            {
                if ( ! isset($tags_names[$prop]))
                {
                    # Most probably cache is outdated, so fetching new fields once again
                    $tags_names = $this->get_tags_names($list_id, TRUE);
                    if ( ! isset($tags_names[$prop]))
                    {
                        continue;
                    }
                }

                if (is_array($value))
                {
                    # Clearing Empty Fields
                    foreach ($value as $f_prop => $f_value)
                    {
                        if (empty($f_value))
                        {
                            unset($value[$f_prop]);
                        }
                    }
                }

                $f_name =  Arr::get($tags_names, $prop);
                $data['meta'][$f_name] = $value;
            }
        }

        unset($list_id, $tags_names);
        return $data;
    }

    /**
     * Rules for form fields
     *
     * @return array
     */
    public static function describe_data_rules()
    {
        return [
            'text' => [
                ['max_length', [':field', 50], 'The maximum length of custom field\'s name should not exceed 50 characters.'],
            ],
            'hidden' => [
                ['max_length', [':value', 50], 'The maximum length of hidden field\'s name should not exceed 50 characters.'],
            ],
        ];
    }

    /**
     * Available automation for integration
     *
     * @return mixed
     */
    public function describe_automations(): array
    {
        $lists           = (array) $this->get_meta('lists', []);
        $groups          = (array) $this->get_meta('groups', []);
        $static_segments = (array) $this->get_meta('static_segments', []);

        return [
            'add_member' => [
                'title' => 'Add member to a list',
                'params_fields' => [
                    'list_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $lists,
                        'title' => 'Add member',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($lists)]],
                        ],
                    ],
                ],
                'is_default' => TRUE,
            ],
            'add_member_with_confirmation' => [
                'title' => 'Request confirmation and add to a list',
                'params_fields' => [
                    'list_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $lists,
                        'title' => 'Add to list',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($lists)]],
                        ],
                    ],
                ],
            ],
            'remove_member' => [
                'title' => 'Remove member from a list',
                'params_fields' => [
                    'list_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $lists,
                        'title' => 'Remove member',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($lists)]],
                        ],
                    ],
                ],
            ],
            'add_member_group' => [
                'title' => 'Add member to a group',
                'params_fields' => [
                    'group_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $groups,
                        'title' => 'Add member to group',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($groups)]],
                        ],
                    ],
                ],
            ],
            'remove_member_group' => [
                'title' => 'Remove member from a group',
                'params_fields' => [
                    'group_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $groups,
                        'title' => 'Remove member from group',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($groups)]],
                        ],
                    ],
                ],
            ],
            'add_member_segment' => [
                'title' => 'Add member to a static segment',
                'params_fields' => [
                    'segment_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $static_segments,
                        'title' => 'Segment',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($static_segments)]],
                        ],
                    ],
                ],
            ],
            'remove_member_segment' => [
                'title' => 'Remove member from a static segment',
                'params_fields' => [
                    'segment_id' => [
                        'classes' => 'i-refreshable',
                        'description' => NULL,
                        'options' => $static_segments,
                        'title' => 'Segment',
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($static_segments)]],
                        ],
                    ],
                ],
            ],
            'add_member_vip' => [
                'title' => 'Add VIP status to a member',
                'params_fields' => [
                    'list_id' => [
                        'classes' => 'i-refreshable',
                        'description' => 'Sheet for a new member',
                        'title' => 'Sheet for a new member',
                        'description_place' => 'title',
                        'description_type' => 'tooltip',
                        'options' => $lists,
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($lists)]],
                        ],
                    ],
                ],
            ],
            'remove_member_vip' => [
                'title' => 'Remove VIP status from a member',
            ],
            'add_member_note' => [
                'title' => 'Add note to a member',
                'params_fields' => [
                    'list_id' => [
                        'classes' => 'i-refreshable',
                        'description' => 'Sheet for a new member',
                        'title' => 'Sheet for a new member',
                        'description_place' => 'title',
                        'description_type' => 'tooltip',
                        'options' => $lists,
                        'type' => 'select',
                        'rules' => [
                            ['not_empty'],
                            ['in_array', [':value', array_keys($lists)]],
                        ],
                    ],
                    'text' => [
                        'title' => 'Note Text',
                        'description' => NULL,
                        'type' => 'text',
                        'rules' => [
                            ['not_empty'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Add member to a list
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     */
    public function add_member(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $subscriber_data = Arr::merge(
            $subscriber_data,
            [
                'list_id' => Arr::get($params, 'list_id'),
            ]
        );

        $this->put_subscriber($email, $subscriber_data, ($subscriber !== NULL));
        unset($subscriber);
    }

    /**
     * Request confirmation and add to a list
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     * @throws Integration_Exception
     */
    public function add_member_with_confirmation(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $subscriber_data = Arr::merge(
            $subscriber_data,
            [
                'list_id'       => Arr::get($params, 'list_id'),
                'status_if_new' => 'pending',
            ]
        );

        $this->put_subscriber($email, $subscriber_data, ($subscriber !== NULL));
        unset($subscriber);
    }

    /**
     * Remove member from a list
     *
     * @param string $email
     * @param array $params
     * @throws Integration_Exception
     */
    public function remove_member(string $email, array $params)
    {
        $list_id = Arr::get($params, 'list_id');

        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/#delete-delete_lists_list_id_members_subscriber_hash
        $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key', ''))
            ->method('DELETE')
            // MailChimp requires JSON-encoded post data
            ->header('Content-Type', 'application/json')
            ->url($this->get_endpoint().'/lists/'.$list_id.'/members/'.md5(mb_strtolower($email)))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            switch ($res->code)
            {
                case 401:
                case 403:
                    throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                    break;
                case 404;
                    return;
                    break;
                case 500:
                    throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                    break;
                case 508:
                    throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                    break;
                default:
                    throw new Integration_Exception(INT_E_WRONG_REQUEST);
                    break;
            }
        }

        unset($res, $list_id);
    }

    /**
     * Add member to a group
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     * @throws Integration_Exception
     */
    public function add_member_group(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $group_id   = Arr::get($params, 'group_id');

        # TODO: Check group_id format
        list($list_id, $interest_category_id, $interest_id) = explode(self::IDS_SEPARATOR, $group_id, 3);

        $subscriber_data = Arr::merge(
            $subscriber_data,
            [
                'list_id'   => $list_id,
                'interests' => [
                    $interest_id => TRUE
                ]
            ]
        );

        $this->put_subscriber($email, $subscriber_data, ($subscriber !== NULL));
        unset($subscriber, $group_id, $list_id, $interest_category_id, $interest_id);
    }

    /**
     * Remove member from a group
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     */
    public function remove_member_group(string $email, array $params)
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $group_id   = Arr::get($params, 'group_id');

        # TODO: Check group_id format
        list($list_id, $interest_category_id, $interest_id) = explode(self::IDS_SEPARATOR, $group_id, 3);

        if ($subscriber !== NULL AND Arr::path($subscriber, 'interests.'.$interest_id, FALSE))
        {
            $subscriber_data = [
                'list_id' => $list_id,
                'interests' => [
                    $interest_id => FALSE
                ]
            ];
            $this->put_subscriber($email, $subscriber_data, TRUE);
            unset($subscriber_data);
        }

        unset($subscriber, $list_id, $interest_category_id, $interest_id);
    }

    /**
     * Add member to a static segment
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     * @throws Integration_Exception
     */
    public function add_member_segment(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $segment_id = Arr::get($params, 'segment_id');

        # TODO: Check segment_id format
        list($list_id, $segment_id) = explode(self::IDS_SEPARATOR, $segment_id, 2);

        if ($subscriber == NULL)
        {
            $subscriber_data = Arr::merge(
                $subscriber_data,
                [
                    'list_id'   => $list_id
                ]
            );

            $this->put_subscriber($email, $subscriber_data, FALSE);
        }

        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/segments/members/#create-post_lists_list_id_segments_segment_id_members
        $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key', ''))
            ->method('POST')
            // MailChimp requires JSON-encoded post data
            ->header('Content-Type', 'application/json')
            ->url($this->get_endpoint().'/lists/'.$list_id.'/segments/'.$segment_id.'/members')
            ->data([
                'email_address' => $email,
                'status'        => 'subscribed',
                'ip_signup'     => Request::$client_ip,
            ])
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            switch ($res->code)
            {
                case 401:
                case 403:
                    throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                    break;
                case 404;
                    throw new Integration_Exception(INT_E_WRONG_PARAMS);
                    break;
                case 500:
                    throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                    break;
                case 508:
                    throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                    break;
                default:
                    throw new Integration_Exception(INT_E_WRONG_REQUEST);
                    break;
            }
        }

        unset($subscriber, $segment_id, $list_id, $res);
    }

    /**
     * Remove member from a static segment
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     * @throws Integration_Exception
     */
    public function remove_member_segment(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $segment_id = Arr::get($params, 'segment_id');

        # TODO: Check group_id format
        list($list_id, $segment_id) = explode(self::IDS_SEPARATOR, $segment_id, 2);

        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/segments/members/#delete-delete_lists_list_id_segments_segment_id_members_subscriber_hash
        $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key', ''))
            ->method('DELETE')
            // MailChimp requires JSON-encoded post data
            ->header('Content-Type', 'application/json')
            ->url($this->get_endpoint().'/lists/'.$list_id.'/segments/'.$segment_id.'/members/'.md5(mb_strtolower($email)))
            ->data([
                'email_address' => $email,
                'status'        => 'subscribed',
                'ip_signup'     => Request::$client_ip,
            ])
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            switch ($res->code)
            {
                case 401:
                case 403:
                    throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                    break;
                case 400:
                case 404:
                    return;
                    break;
                case 500:
                    throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                    break;
                case 508:
                    throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                    break;
                default:
                    throw new Integration_Exception(INT_E_WRONG_REQUEST);
                    break;
            }
        }
        unset($res, $list_id, $segment_id);
    }

    /**
     * Add VIP status to a member
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     */
    public function add_member_vip(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $list_id    = Arr::get($params, 'list_id');

        $subscriber_data = Arr::merge(
            $subscriber_data,
            [
                'list_id' => Arr::get($subscriber, 'list_id', $list_id),
                'vip'     => TRUE
            ]
        );
        $this->put_subscriber($email, $subscriber_data, ($subscriber !== NULL));
        unset($subscriber, $subscriber_data, $list_id);
    }

    /**
     * Remove VIP status from a member
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     */
    public function remove_member_vip(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        if ($subscriber !== NULL)
        {
            $subscriber_data = Arr::merge(
                $subscriber_data,
                [
                    'list_id' => Arr::get($subscriber, 'list_id'),
                    'vip'     => FALSE
                ]
            );
            $this->put_subscriber($email, $subscriber_data, TRUE);
        }
        unset($subscriber);
    }

    /**
     * Add note to a member
     *
     * @param string $email
     * @param array $params
     * @param array $subscriber_data
     * @throws Integration_Exception
     */
    public function add_member_note(string $email, array $params, array $subscriber_data = [])
    {
        $subscriber = $this->get_subscriber($email, FALSE);
        $list_id    = Arr::get($params, 'list_id');

        # New subscriber
        if ($subscriber === NULL)
        {
            $subscriber_data = Arr::merge(
                $subscriber_data,
                [
                    'list_id' => $list_id
                ]
            );
            $subscriber = $this->put_subscriber($email, $subscriber_data, FALSE);
        }

        $list_id = Arr::get($subscriber, 'list_id', $list_id);

        # http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/notes/#create-post_lists_list_id_members_subscriber_hash_notes
        $res = Integration_Request::factory()
            ->http_basic_auth('user', $this->get_credentials('api_key', ''))
            ->method('POST')
            // MailChimp requires JSON-encoded post data
            ->header('Content-Type', 'application/json')
            ->url($this->get_endpoint().'/lists/'.$list_id.'/members/'.md5(mb_strtolower($email)).'/notes')
            ->data([
                'note' => Arr::get($params, 'text', '')
            ])
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $res->is_successful())
        {
            switch ($res->code)
            {
                case 401:
                case 403:
                    throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
                    break;
                case 404;
                    throw new Integration_Exception(INT_E_WRONG_PARAMS);
                    break;
                case 500:
                    throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
                    break;
                case 508:
                    throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
                    break;
                default:
                    throw new Integration_Exception(INT_E_WRONG_REQUEST);
                    break;
            }
        }
        unset($subscriber, $list_id, $res);
    }
}
