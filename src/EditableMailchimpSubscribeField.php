<?php

namespace WebFox\UserFormsMailchimp;

use DrewM\MailChimp\MailChimp;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\UserForms\Model\EditableFormField;
use SilverStripe\UserForms\Model\EditableFormField\EditableFieldGroup;
use SilverStripe\UserForms\Model\EditableFormField\EditableFieldGroupEnd;
use SilverStripe\UserForms\Model\EditableFormField\EditableFormStep;

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
    private static $db = [
        'ListID' => 'Varchar(100)',
        'EmailField' => 'Varchar(100)',
        'FirstNameField' => 'Varchar(100)',
        'LastNameField' => 'Varchar(100)',
    ];

    /** @var string Singular Name */
    private static $singular_name = 'Mailchimp Signup Field';

    /** @var string Plural Name */
    private static $plural_name = 'Mailchimp Signup Fields';

    private static $table_name = 'EditableMailchimpSubscribeField';

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
                [
                    'ParentID' => $parent->ID,
                    'ID:not' => $fieldId,
                    'ClassName:not' => [
                        EditableFormStep::class,
                        EditableFieldGroup::class,
                        EditableFieldGroupEnd::class,
                    ],
                ]
            )->map('Name', 'Title');

            $fields->addFieldsToTab(
                'Root.Mailchimp',
                [
                    DropdownField::create('ListID', _t('EditableFormField.MailchimpList', 'List ID'), $lists)
                        ->setEmptyString(''),
                    DropdownField::create('EmailField', _t('EditableFormField.MailchimpEmailField', 'Email Field'), $otherFields)
                        ->setEmptyString(''),
                    DropdownField::create('FirstNameField', _t('EditableFormField.MailchimpFirstNameField', 'First Name Field'), $otherFields)
                        ->setEmptyString(''),
                    DropdownField::create('LastNameField', _t('EditableFormField.MailchimpLastNameField', 'Last Name Field'), $otherFields)
                        ->setEmptyString(''),
                ]
            );
        });

        return parent::getCMSFields();
    }

    /**
     * @return FormField|false
     */
    public function getFormField()
    {
        if ($this->ListID && $this->EmailField) {
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

        if (!$subscribe || !$this->ListID) {
            return 'Unable to subscribe';
        }

        try {
            $mailchimp = new MailChimp($this->config()->get('api_key'));

            // Check for proxy settings
            if ($this->config()->get('proxy')) {
                $mailchimp->setProxy(
                    $this->config()->get('proxy_url'),
                    (int) $this->config()->get('proxy_port'),
                    $this->config()->get('proxy_ssl'),
                    $this->config()->get('proxy_user'),
                    $this->config()->get('proxy_password')
                );
            }

            $mailchimp->post('lists/' . $this->ListID . '/members', [
                'email_address' => $data[$this->EmailField],
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => $data[$this->FirstNameField],
                    'LNAME' => $data[$this->LastNameField],
                ],
            ]);

            return 'Subscribed';
        } catch (\Exception $e) {
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
            $mailchimp = new MailChimp($this->config()->get('api_key'));

            // Check for proxy settings
            if ($this->config()->get('proxy')) {
                $mailchimp->setProxy(
                    $this->config()->get('proxy_url'),
                    (int) $this->config()->get('proxy_port'),
                    $this->config()->get('proxy_ssl'),
                    $this->config()->get('proxy_user'),
                    $this->config()->get('proxy_password')
                );
            }

            $lists = $mailchimp->get('lists', ['fields' => 'lists.id,lists.name', 'count' => 100]);

            $lists = new ArrayList($lists['lists']);

            self::$lists = $lists->map('id', 'name')->toArray();
        }

        return self::$lists;
    }
}
