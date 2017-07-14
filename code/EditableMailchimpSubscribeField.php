<?php

/**
 * Creates an editable field that allows users to sign up to mailchimp
 *
 * @author Webfox Developments <developers@webfox.co.nz>
 *
 * @property int ListID
 * @property string EmailField
 * @property string FirstNameField
 * @property string LastNameField
 *
 * @method UserDefinedForm Parent
 */
class EditableMailchimpSubscribeField extends EditableFormField
{
    /** @var array */
    private static $db = array(
        'ListID' => 'Varchar(100)',
        'EmailField' => 'Varchar(100)',
        'FirstNameField' => 'Varchar(100)',
        'LastNameField' => 'Varchar(100)'
    );

    /** @var string Singular Name */
    private static $singular_name = 'Mailchimp Signup Field';

    /** @var string Plural Name */
    private static $plural_name = 'Mailchimp Signup Fields';

    /** @var string Field Icon */
    private static $icon = 'userforms-mailchimp/icons/editablemailchimpsubscribefield.png';

    /** @var array|null Lists Map */
    private static $lists;

    /** @var array|null Fields Map */
    private static $fields;

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fieldId = $this->ID;
        $parent = $this->Parent();
        $lists = $this->getLists();

        $this->beforeUpdateCMSFields(function (FieldList $fields) use ($fieldId, $parent, $lists) {
            /** @var DataList $otherFields */
            $otherFields = EditableFormField::get()->filter(
                array(
                    'ParentID' => $parent->ID,
                    'ID:not' => $fieldId,
                    'ClassName:not' => array(
                        'EditableFormStep',
                        'EditableFieldGroup',
                        'EditableFieldGroupEnd',
                    )
                )
            )->map('Name', 'Title');

            if (class_exists('Mailchimp\Mailchimp')) {
                $fields->addFieldsToTab(
                    'Root.Mailchimp',
                    array(
                        DropdownField::create("ListID", _t('EditableFormField.MailchimpList', 'List ID'), $lists),
                        DropdownField::create("EmailField", _t('EditableFormField.MailchimpEmailField', 'Email Field'), $otherFields),
                        DropdownField::create("FirstNameField", _t('EditableFormField.MailchimpFirstNameField', 'First Name Field'), $otherFields),
                        DropdownField::create("LastNameField", _t('EditableFormField.MailchimpLastNameField', 'Last Name Field'), $otherFields)
                    )
                );
            } else {
                $fields->addFieldsToTab(
                    'Root.Mailchimp',
                    array(
                        LiteralField::create('DependencyMissing', '<div class="message bad">The dependency <em>pacely/mailchimp-apiv3</em> is missing. Please reinstall this module via composer')
                    )
                );
            }
        });

        return parent::getCMSFields();
    }

    /**
     * @return FormField|false
     */
    public function getFormField()
    {
        if (class_exists('Mailchimp\Mailchimp') && $this->ListID && $this->EmailField && $this->FirstNameField && $this->LastNameField) {
            return CheckboxField::create($this->Name, $this->Title);
        }

        return false;
    }

    /**
     * @param $data
     * @return string
     */
    public function getValueFromData($data)
    {
        $subscribe = isset($data[$this->Name]);

        if (!$subscribe || !class_exists('Mailchimp\Mailchimp')) {
            return 'Unable to subscribe';
        }

        try {
            $mc = new \Mailchimp\Mailchimp($this->config()->get('api_key'));

            // Check for proxy settings
            if ($this->config()->get('proxy')) {
                $mc->setProxy(
                    $this->config()->get('proxy_url'),
                    (int)$this->config()->get('proxy_port'),
                    $this->config()->get('proxy_ssl'),
                    $this->config()->get('proxy_user'),
                    $this->config()->get('proxy_password')
                );
            }

            $mc->post('lists/' . $this->ListID . '/members', array(
                "email_address" => $data[$this->EmailField],
                "status" => "subscribed",
                "merge_fields" => array(
                    "FNAME" => $data[$this->FirstNameField],
                    "LNAME" => $data[$this->LastNameField]
                )
            ));

            return 'Subscribed';
        } catch (Exception $e) {
            return 'Failed (' . json_decode($e->getMessage())->title . ')';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getIcon()
    {
        return self::$icon;
    }

    /**
     * Return a map of available Mailing Lists
     *
     * @return array
     */
    public function getLists()
    {
        if (!class_exists('Mailchimp\Mailchimp')) {
            return array();
        }

        if (!self::$lists) {
            $mc = new \Mailchimp\Mailchimp($this->config()->get('api_key'));

            // Check for proxy settings
            if ($this->config()->get('proxy')) {
                $mc->setProxy(
                    $this->config()->get('proxy_url'),
                    (int)$this->config()->get('proxy_port'),
                    $this->config()->get('proxy_ssl'),
                    $this->config()->get('proxy_user'),
                    $this->config()->get('proxy_password')
                );
            }

            /** @var \Illuminate\Support\Collection $lists */
            $lists = $mc->request('lists', array('fields' => 'lists.id,lists.name', 'count' => 100));

            self::$lists = $lists->pluck('name', 'id')->toArray();
        }

        return self::$lists;
    }
}
