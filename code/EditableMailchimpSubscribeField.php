<?php

/**
 * Creates an editable field that allows users to sign up to mailchimp
 *
 *
 * @method UserDefinedForm Parent()
 */
class EditableMailchimpSubscribeField extends EditableFormField {

	static $singular_name = 'Mailchimp Signup Field';

	static $plural_name = 'Mailchimp Signup Fields';

	static $icon = '/userforms-mailchimp/icons/editablemailchimpsubscribefield.png';

	static $lists;

	static $fields;

	public function __construct($record = null, $isSingleton = false, $model = null) {
		parent::__construct($record, $isSingleton, $model);

	}

	public function getFieldConfiguration() {
		$listID = $this->getSetting('ListID');

		/** @var HasManyList $otherFields */
		$otherFields = $this->Parent()->Fields();

		$otherFields = $otherFields->map('Name', 'Title')->toArray();

		$emailField     = $this->getSetting('EmailField');
		$firstNameField = $this->getSetting('FirstNameField');
		$lastNameField  = $this->getSetting('LastNameField');

		$pre = "Fields[$this->ID][CustomSettings]";

		$fields = new FieldList([
			new DropdownField("{$pre}[ListID]", _t('EditableFormField.MailchimpList', 'List ID'), $this->getLists(), $listID),
			new DropdownField("{$pre}[EmailField]", _t('EditableFormField.MailchimpEmailField', 'Email Field'), $otherFields, $emailField),
			new DropdownField("{$pre}[FirstNameField]", _t('EditableFormField.MailchimpFirstNameField', 'First Name Field'), $otherFields, $firstNameField),
			new DropdownField("{$pre}[LastNameField]", _t('EditableFormField.MailchimpLastNameField', 'Last Name Field'), $otherFields, $lastNameField)
		]);

		return $fields;
	}

	public function getFormField() {
		if ($this->getSetting('ListID') && $this->getSetting('EmailField') && $this->getSetting('FirstNameField') && $this->getSetting('LastNameField')) {
			return new CheckboxField($this->Name, $this->Title);
		}

		return false;
	}

	public function getValueFromData($data) {

		$subscribe = isset($data[$this->Name]);

		if ($subscribe) {

			try {
				$mc      = new \Mailchimp\Mailchimp($this->config()->get('api_key'));
				$request = $mc->post('lists/' . $this->getSetting('ListID') . '/members', [
					"email_address" => $data[$this->getSetting('EmailField')],
					"status"        => "subscribed",
					"merge_fields"  => [
						"FNAME" => $data[$this->getSetting('FirstNameField')],
						"LNAME" => $data[$this->getSetting('LastNameField')]
					]
				]);

				return 'Subscribed';
			} catch (Exception $e) {
				return 'Failed (' . json_decode($e->getMessage())->detail . ')';
			}
		}

		return "No";
	}

	public function getIcon() {
		return self::$icon;
	}

	public function getLists() {
		if (!self::$lists) {
			$mc = new \Mailchimp\Mailchimp($this->config()->get('api_key'));

			/** @var \Illuminate\Support\Collection $lists */
			$lists = $mc->request('lists', ['fields' => 'lists.id,lists.name', 'count' => 100]);

			self::$lists = $lists->lists('name', 'id')->toArray();
		}

		return self::$lists;
	}
}
