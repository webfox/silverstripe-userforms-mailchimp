<?php

/**
 * Creates an editable field that allows users to sign up to mailchimp
 *
 * @author Webfox Developments <developers@webfox.co.nz>
 * 
 * @method UserDefinedForm Parent
 */
class EditableMailchimpSubscribeField extends EditableFormField
{
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
    public function getFieldConfiguration()
    {
        $listID = $this->getSetting('ListID');

        /** @var Form $parent */
        $parent = $this->Parent();

        /** @var HasManyList $otherFields */
        $otherFields = $parent->Fields();

        $otherFields = $otherFields->map('Name', 'Title')->toArray();

        $emailField = $this->getSetting('EmailField');
        $firstNameField = $this->getSetting('FirstNameField');
        $lastNameField = $this->getSetting('LastNameField');

        $pre = "Fields[$this->ID][CustomSettings]";

        $fields = new FieldList(
            array(
                DropdownField::create("{$pre}[ListID]", _t('EditableFormField.MailchimpList', 'List ID'), $this->getLists(), $listID),
                DropdownField::create("{$pre}[EmailField]", _t('EditableFormField.MailchimpEmailField', 'Email Field'), $otherFields, $emailField),
                DropdownField::create("{$pre}[FirstNameField]", _t('EditableFormField.MailchimpFirstNameField', 'First Name Field'), $otherFields, $firstNameField),
                DropdownField::create("{$pre}[LastNameField]", _t('EditableFormField.MailchimpLastNameField', 'Last Name Field'), $otherFields, $lastNameField)
            )
        );

        return $fields;
    }

    /**
     * @return FormField|false
     */
    public function getFormField()
    {
        if ($this->getSetting('ListID') && $this->getSetting('EmailField') && $this->getSetting('FirstNameField') && $this->getSetting('LastNameField')) {
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

        if (!$subscribe) {
            return 'No';
        }
        
        try {
            $mc = new \Mailchimp\Mailchimp($this->config()->get('api_key'));

            // Check for proxy settings
            if ($this->config()->get('proxy')) {
                $mc->setProxy(
                    $this->config()->get('proxy_url'),
                    $this->config()->get('proxy_port'),
                    $this->config()->get('proxy_ssl'),
                    $this->config()->get('proxy_user'),
                    $this->config()->get('proxy_password')
                );
            }

            $request = $mc->post('lists/' . $this->getSetting('ListID') . '/members', array(
                "email_address" => $data[$this->getSetting('EmailField')],
                "status" => "subscribed",
                "merge_fields" => array(
                    "FNAME" => $data[$this->getSetting('FirstNameField')],
                    "LNAME" => $data[$this->getSetting('LastNameField')]
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
        if (!self::$lists) {
            $mc = new \Mailchimp\Mailchimp($this->config()->get('api_key'));

            // Check for proxy settings
            if ($this->config()->get('proxy')) {
                $mc->setProxy(
                    $this->config()->get('proxy_url'),
                    $this->config()->get('proxy_port'),
                    $this->config()->get('proxy_ssl'),
                    $this->config()->get('proxy_user'),
                    $this->config()->get('proxy_password')
                );
            }

            /** @var \Illuminate\Support\Collection $lists */
            $lists = $mc->request('lists', array('fields' => 'lists.id,lists.name', 'count' => 100));

            self::$lists = $lists->lists('name', 'id')->toArray();
        }

        return self::$lists;
    }
}
