<?php

/*
 +-----------------------------------------------------------------------+
 | Vacation Module for RoundCube                                         |
 |                                                                       |
 | Copyright (C) 2009 Boris HUISGEN <bhuisgen@hbis.fr>                   |
 | Licensed under the GNU GPL                                            |
 +-----------------------------------------------------------------------+
 */

define ('PLUGIN_NOERROR', 0);
define ('PLUGIN_ERROR_DEFAULT', -1);
define ('PLUGIN_ERROR_CONNECT', -2);
define ('PLUGIN_ERROR_PROCESS', -3);

class vacation extends rcube_plugin
{
	public $task = 'settings';
	private $rc;
	private $obj;

	/*
	 * Initializes the plugin.
	 */
	public function init()
	{
		$rcmail = rcmail::get_instance();
		$this->rc = &$rcmail;

		$this->add_texts('localization/', true);
		$this->rc->output->add_label('vacation');

		$this->register_action('plugin.vacation', array($this, 'vacation_init'));
		$this->register_action('plugin.vacation-save', array($this, 'vacation_save'));
		$this->include_script('vacation.js');

		$this->load_config();
			
		require_once ($this->home . '/lib/rcube_vacation.php');
		$this->obj = new rcube_vacation ();
	}

	/*
	 * Plugin initialization function.
	 */
	public function vacation_init()
	{
		$this->read_data ();

		$this->register_handler('plugin.body', array($this, 'vacation_form'));
		$this->rc->output->set_pagetitle($this->gettext('vacation'));
		$this->rc->output->send('plugin');
	}

	/*
	 * Plugin save function.
	 */
	public function vacation_save()
	{
		$this->write_data ();

		$this->register_handler('plugin.body', array($this, 'vacation_form'));
		$this->rc->output->set_pagetitle($this->gettext('vacation'));
		rcmail_overwrite_action('plugin.vacation');
		$this->rc->output->send('plugin');
	}

	/*
	 * Plugin UI form function.
	 */
	public function vacation_form()
	{
		$table = new html_table(array('cols' => 2));

		$field_id = 'vacationenable';
		$input_vacationenable = new html_checkbox(array('name' => '_vacationenable',
				 'id' => $field_id, 'value' => 1));
		$table->add('title', html::label($field_id, Q($this->gettext('vacationenable'))));
		$table->add(null, $input_vacationenable->show($this->obj->is_vacation_enable() ? 1 : 0));

		if ($this->rc->config->get('vacation_gui_vacationsubject', FALSE))
		{
			$field_id = 'vacationsubject';
			$input_vacationsubject = new html_inputfield(array('name' => '_vacationsubject',
				 'id' => $field_id, 'size' => 40));
			$table->add('title', html::label($field_id, Q($this->gettext('vacationsubject'))));
			$table->add(null, $input_vacationsubject->show($this->obj->get_vacation_subject()));
		}
		
		$field_id = 'vacationmessage';
		if ($this->rc->config->get('vacation_gui_vacationmessage_html', FALSE))
		{
			$this->rc->output->add_label('converting', 'editorwarning');
			// FIX: use identity mode for minimal functions
			rcube_html_editor('identity');

			$text_vacationmessage = new html_textarea(array('name' => '_vacationmessage',
				 'id' => $field_id, 'spellcheck' => 1, 'rows' => 6, 'cols' => 40, 'class' => 'mce_editor'));
		}
		else
		{
			$text_vacationmessage = new html_textarea(array('name' => '_vacationmessage',
				 'id' => $field_id, 'spellcheck' => 1, 'rows' => 6, 'cols' => 40));
		}

		$table->add('title', html::label($field_id, Q($this->gettext('vacationmessage'))));
		$table->add(null, $text_vacationmessage->show($this->obj->get_vacation_message()));
		
		if ($this->rc->config->get('vacation_gui_vacationforwarder', FALSE))
		{
			$field_id = 'vacationforwarder';
			$input_vacationforwarder = new html_inputfield(array('name' => '_vacationforwarder',
				 'id' => $field_id, 'size' => 20));
			$table->add('title', html::label($field_id, Q($this->gettext('vacationforwarder'))));
			$table->add(null, $input_vacationforwarder->show($this->obj->get_vacation_forwarder()));
		}

		$out = html::div(array('class' => "settingsbox", 'style' => "margin:0"),
		html::div(array('id' => "prefs-title", 'class' => 'boxtitle'), $this->gettext('vacation')) .
		html::div(array('style' => "padding:15px"), $table->show() .
		html::p(null, $this->rc->output->button(array(
		      'command' => 'plugin.vacation-save', 'type' => 'input', 'class' => 'button mainaction', 'label' => 'save')))));

		$this->rc->output->add_gui_object('vacationform', 'vacation-form');

		return $this->rc->output->form_tag(array('id' => 'vacation-form', 'name' => 'vacation-form', 'method' => 'post', 'action' => './?_task=settings&_action=plugin.vacation-save'), $out);
	}

	/*
	 * Reads plugin data.
	 */
	public function read_data()
	{
		$driver = $this->home . '/lib/drivers/' . $this->rc->config->get('vacation_driver', 'sql').'.php';

		if (!is_readable($driver))
		{
			raise_error(array('code' => 600, 'type' => 'php', 'file' => __FILE__,
			 'message' => "Vacation plugin: Unable to open driver file $driver"), true, false);

			return $this->gettext('internalerror');
		}

		require_once($driver);

		if (!function_exists('vacation_read'))
		{
			raise_error(array('code' => 600, 'type' => 'php', 'file' => __FILE__,
			 'message' => "Vacation plugin: Broken driver: $driver"), true, false);

			return $this->gettext('internalerror');
		}

		$data['username'] = $this->obj->get_username();
		$data['email'] = $this->obj->get_email();
		$data['email_local'] = $this->obj->get_email_local();
		$data['email_domain'] = $this->obj->get_email_domain();
		$data['vacation_enable'] = $this->obj->is_vacation_enable();
		$data['vacation_subject'] = ($this->obj->get_vacation_subject() == '' ?
			$this->rc->config->get('vacation_gui_vacationsubject_default') :
			$this->obj->get_vacation_subject());
		$data['vacation_message'] = ($this->obj->get_vacation_message() == '' ?
			$this->rc->config->get('vacation_gui_vacationmessage_default') :
			$this->obj->get_vacation_message());
		$data['vacation_forwarder'] = $this->obj->get_vacation_forwarder();

		$ret = vacation_read ($data);
		switch ($ret)
		{
			case PLUGIN_ERROR_DEFAULT:
				{
					$this->rc->output->command('display_message',
					$this->gettext('vacationdriverdefaulterror'), 'error');
						
					return FALSE;
				}

			case PLUGIN_ERROR_CONNECT:
				{
					$this->rc->output->command('display_message',
					$this->gettext('vacationdriverconnecterror'), 'error');
						
					return FALSE;
				}

			case PLUGIN_ERROR_PROCESS:
				{
					$this->rc->output->command('display_message',
					$this->gettext('vacationdriverprocesserror'), 'error');
						
					return FALSE;
				}

			case PLUGIN_NOERROR:
			default:
				{
					break;
				}
		}

		if (isset($data['email']))
		{
			$this->obj->set_email($data['email']);
		}
		
		if (isset($data['email_local']))
		{
			$this->obj->set_email_local($data['email_local']);
		}
		
		if (isset($data['email_domain']))
		{
			$this->obj->set_email_domain($data['email_domain']);
		}
		
		if (isset($data['vacation_enable']))
		{
			$this->obj->set_vacation_enable($data['vacation_enable']);
		}
		
		if (isset($data['vacation_subject']))
		{
			$this->obj->set_vacation_subject($data['vacation_subject']);
		}
		
		if (isset($data['vacation_message']))
		{
			$this->obj->set_vacation_message($data['vacation_message']);
		}
		
		if (isset($data['vacation_forwarder']))
		{
			$this->obj->set_vacation_forwarder($data['vacation_forwarder']);
		}

		return TRUE;
	}

	/*
	 * Writes plugin data.
	 */
	public function write_data()
	{
		if (isset($_POST['_vacationenable']))
		{
			$this->obj->set_vacation_enable(TRUE);
		}
		else
		{
			$this->obj->set_vacation_enable(FALSE);
		}

		$this->obj->set_vacation_subject($_POST['_vacationsubject']);
		$this->obj->set_vacation_message($_POST['_vacationmessage']);
		$this->obj->set_vacation_forwarder($_POST['_vacationforwarder']);

		$driver = $this->home . '/lib/drivers/' . $this->rc->config->get('vacation_driver', 'sql').'.php';

		if (!is_readable($driver))
		{
			raise_error(array('code' => 600, 'type' => 'php', 'file' => __FILE__,
			 'message' => "Vacation plugin: Unable to open driver file $driver"), true, false);

			return $this->gettext('internalerror');
		}

		require_once($driver);

		if (!function_exists('vacation_write'))
		{
			raise_error(array('code' => 600, 'type' => 'php', 'file' => __FILE__,
			 'message' => "Vacation plugin: Broken driver: $driver"), true, false);

			return $this->gettext('internalerror');
		}

		$data['username'] = $this->obj->get_username();
		$data['email'] = $this->obj->get_email();
		$data['email_local'] = $this->obj->get_email_local();
		$data['email_domain'] = $this->obj->get_email_domain();
		$data['vacation_enable'] = $this->obj->is_vacation_enable();
		$data['vacation_subject'] = $this->obj->get_vacation_subject();
		$data['vacation_message'] = $this->obj->get_vacation_message();
		$data['vacation_forwarder'] = $this->obj->get_vacation_forwarder();

		$ret = vacation_write ($data);
		switch ($ret)
		{
			case PLUGIN_ERROR_DEFAULT:
				{
					$this->rc->output->command('display_message',
					$this->gettext('vacationdriverdefaulterror'), 'error');
						
					return FALSE;
				}

			case PLUGIN_ERROR_CONNECT:
				{
					$this->rc->output->command('display_message',
					$this->gettext('vacationdriverconnecterror'), 'error');
						
					return FALSE;
				}

			case PLUGIN_ERROR_PROCESS:
				{
					$this->rc->output->command('display_message',
					$this->gettext('vacationdriverprocesserror'), 'error');
						
					return FALSE;
				}

			case PLUGIN_NOERROR:
			default:
				{
					$this->rc->output->command('display_message',
					$this->gettext('successfullysaved'), 'confirmation');

					break;
				}
		}

		if (isset($data['email']))
		{
			$this->obj->set_email($data['email']);
		}
		
		if (isset($data['email_local']))
		{
			$this->obj->set_email_local($data['email_local']);
		}
		
		if (isset($data['email_domain']))
		{
			$this->obj->set_email_domain($data['email_domain']);
		}
		
		if (isset($data['vacation_enable']))
		{
			$this->obj->set_vacation_enable($data['vacation_enable']);
		}
		
		if (isset($data['vacation_subject']))
		{
			$this->obj->set_vacation_subject($data['vacation_subject']);
		}
		
		if (isset($data['vacation_message']))
		{
			$this->obj->set_vacation_message($data['vacation_message']);
		}
		
		if (isset($data['vacation_forwarder']))
		{
			$this->obj->set_vacation_forwarder($data['vacation_forwarder']);
		}

		return TRUE;
	}
}
?>
