<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/20/2018
 * Time: 3:45 PM
 */
echo "<link rel='stylesheet' href='".$module->getUrl('css/jquery-ui.min.css')."'>
	<link rel='stylesheet' href='".$module->getUrl('css/jquery-ui.theme.min.css')."'>
	<link rel='stylesheet' href='".$module->getUrl('css/bootstrap.min.css')."'>
	<link rel='stylesheet' href='".$module->getUrl('css/bootstrap-theme.css')."'>
	<link href='".$module->getUrl('css/styles.css')."' rel='stylesheet'>";

define("PROJ_PROD_SETTING","project_production");
define("FORM_NAME_SETTING","form_names");
define("FIELD_NAME_SETTING","field_names");
define("FIELD_VALUE_SETTING","field_value");
define("USER_NEW_SETTING","user_new");
define("USER_EDIT_SETTING","user_edit");
define("PASTDUE_SETTING","past_due");
define("RECORD_COUNT_SETTING","record_count");