<?php
/**
* $Id: index.php,v 1.87 2007-03-29 12:34:12 thorstenr Exp $
*
* The main admin backend index file
*
* @author       Thorsten Rinne <thorsten@phpmyfaq.de>
* @author       Bastian Poettner <bastian@poettner.net>
* @author       Meikel Katzengreis <meikel@katzengreis.com>
* @author       Minoru TODA <todam@netjapan.co.jp>
* @since        2002-09-16
* @copyright    (c) 2001-2007 phpMyFAQ Team
*
* The contents of this file are subject to the Mozilla Public License
* Version 1.1 (the "License"); you may not use this file except in
* compliance with the License. You may obtain a copy of the License at
* http://www.mozilla.org/MPL/
*
* Software distributed under the License is distributed on an "AS IS"
* basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
* License for the specific language governing rights and limitations
* under the License.
*/

define('PMF_ROOT_DIR', dirname(dirname(__FILE__)));

//
// Check if data.php exist -> if not, redirect to installer
//
if (!file_exists(PMF_ROOT_DIR.'/inc/data.php')) {
    header("Location: ".str_replace('admin/index.php', '', $_SERVER["PHP_SELF"])."install/installer.php");
    exit();
}

//
// Prepend and start the PHP session
//
define('IS_VALID_PHPMYFAQ_ADMIN', null);
require_once(PMF_ROOT_DIR.'/inc/Init.php');
PMF_Init::cleanRequest();
session_name('pmf_auth_'.$faqconfig->get('phpMyFAQToken'));
session_start();

// Include classes and functions
require_once(PMF_ROOT_DIR.'/inc/Utils.php');
require_once(PMF_ROOT_DIR.'/inc/Category.php');
require_once(PMF_ROOT_DIR.'/inc/Faq.php');
require_once(PMF_ROOT_DIR.'/inc/Linkverifier.php');
require_once(PMF_ROOT_DIR.'/inc/Tags.php');
require_once(PMF_ROOT_DIR.'/inc/PMF_User/CurrentUser.php');
require_once(PMF_ROOT_DIR.'/inc/libs/idna_convert.class.php');
$IDN = new idna_convert;

// get language (default: english)
$pmf = new PMF_Init();
$LANGCODE = $pmf->setLanguage((isset($PMF_CONF['main.languageDetection']) ? true : false), $PMF_CONF['main.language']);
// Preload English strings
require_once ('../lang/language_en.php');

if (isset($LANGCODE) && PMF_Init::isASupportedLanguage($LANGCODE)) {
    // Overwrite English strings with the ones we have in the current language
    require_once('../lang/language_'.$LANGCODE.'.php');
} else {
    $LANGCODE = 'en';
}

//
// Create a new FAQ object
//
$faq = new PMF_Faq($db, $LANGCODE);

// use mbstring extension if available
$valid_mb_strings = array('ja', 'en');
if (function_exists('mb_language') && in_array($PMF_LANG['metaLanguage'], $valid_mb_strings)) {
    mb_language($PMF_LANG['metaLanguage']);
    mb_internal_encoding($PMF_LANG['metaCharset']);
}

// TODO: Manage the 'Rembember me' Cookie also under 2.0.0.
// authenticate current user
$auth = null;
if (isset($_POST['faqpassword']) and isset($_POST['faqusername'])) {
    // login with username and password
    $user = new PMF_CurrentUser();
    $faqusername = $db->escape_string($_POST['faqusername']);
    $faqpassword = $db->escape_string($_POST['faqpassword']);
    if ($user->login($faqusername, $faqpassword)) {
        // login, if user account is NOT blocked
        if ($user->getStatus() != 'blocked') {
            $auth = true;
        } else {
            $error = $PMF_LANG['ad_auth_fail'].' ('.$faqusername.' / *)';
            $user = null;
            unset($user);
        }
    } else {
        // error
        adminlog('Loginerror\nLogin: '.$faqusername.'\nPass: ********');
        $error = $PMF_LANG['ad_auth_fail'].' ('.$faqusername.' / *)';
        $user = null;
        unset($user);
        $_REQUEST['action'] = '';
    }
} else {
    // authenticate with session information
    $user = PMF_CurrentUser::getFromSession($faqconfig->get('main.ipCheck'));
    if ($user) {
        $auth = true;
    } else {
        // error
        adminlog('Session expired\nSession-ID: '.session_id());
        $error = $PMF_LANG['ad_auth_sess'];
        $user = null;
        unset($user);
        $_REQUEST['action'] = '';
    }
}

// get user rights
$permission = array();
if (isset($auth)) {
    // read all rights, set them FALSE
    $allRights = $user->perm->getAllRightsData();
    foreach ($allRights as $right) {
        $permission[$right['name']] = false;
    }
    // check user rights, set them TRUE
    $allUserRights = $user->perm->getAllUserRights($user->getUserId());
    foreach ($allRights as $right) {
        if (in_array($right['right_id'], $allUserRights))
            $permission[$right['name']] = true;
    }
}

// logout
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'logout' && $auth) {
    $user->deleteFromSession();
    $user = null;
    unset($user);
    $auth = null;
    unset($auth);
}

//
// Get current admin user and group id - default: -1
//
if (isset($user) && is_object($user)) {
    $current_admin_user   = $user->getUserId();
    if (is_a($user->perm, "PMF_PermMedium")) {
        $current_admin_groups = $user->perm->getUserGroups($current_admin_user);
    } else {
        $current_admin_groups = array(-1);
    }
    if (0 == count($current_admin_groups)) {
        $current_admin_groups = array(-1);
    }
}

// FIXME: remove this dummy declaration when the all of the pages will NOT use it for building the links
$linkext = '?uin=';

//
// Get action from _GET and _POST first
$_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
$_ajax   = isset($_REQUEST['ajax']) ? $_REQUEST['ajax'] : null;

// if performing AJAX operation, needs to branch before header.php
if (isset($auth)) {
    if (isset($_action) && isset($_ajax)) {
        if ($_action == 'ajax') {
            switch ($_ajax) {
                // Link verification
                case 'verifyURL':       require_once('ajax.verifyurl.php'); break;
                case 'onDemandURL':     require_once('ajax.ondemandurl.php'); break;

                // User management
                case 'user_list':       require_once('ajax.user_list.php'); break;
                case 'group_list':       require_once('ajax.group_list.php'); break;

                // Configuration management
                case 'config_list':     require_once('ajax.config_list.php'); break;

                // Tags management
                case 'tags_list':     require_once('ajax.tags_list.php'); break;
            }
        exit();
        }
    }
}

// are we running a PMF export file request?
if ((isset($_REQUEST["action"])) && ($_REQUEST["action"] == "exportfile")) {
    require_once("export.file.php");
    exit();
}

// Header of the admin page inlcuding the navigation
require_once ("header.php");

// User is authenticated
if (isset($auth)) {
    if (isset($_action) && ($_action)) {
        // the various sections of the admin area
        switch ($_action) {
            // functions for user administration
            case 'user':                    require_once('user.php'); break;
            case 'group':                   require_once('group.php'); break;
            // functions for record administration
            case "view":                    require_once ("record.show.php"); break;
            case "accept":                  require_once ("record.show.php"); break;
            case "zeichan":                 require_once ("record.show.php"); break;
            case "takequestion":            require_once ("record.edit.php"); break;
            case "editentry":               require_once ("record.edit.php"); break;
            case "editpreview":             require_once ("record.edit.php"); break;
            case "delcomment":              require_once ("record.delcommentform.php"); break;
            case "deletecomment":           require_once ("record.delcomment.php"); break;
            case "insertentry":             require_once ("record.add.php"); break;
            case "saveentry":               require_once ("record.save.php"); break;
            case "delentry":                require_once ("record.delete.php"); break;
            case "delatt":                  require_once ("record.delatt.php"); break;
            case "question":                require_once ("record.delquestion.php"); break;
            case 'comments':                require_once ('record.comments.php'); break;
            // news administraion
            case "news":                    require_once ("news.php"); break;
            // category administration
            case 'content':
            case 'category':
            case 'savecategory':
            case 'updatecategory':
            case 'removecategory':
            case 'changecategory':
            case 'pastecategory':           require_once ('category.main.php'); break;
            case "addcategory":             require_once ("category.add.php"); break;
            case "editcategory":            require_once ("category.edit.php"); break;
            case "translatecategory":       require_once ("category.translate.php"); break;
            case "deletecategory":          require_once ("category.delete.php"); break;
            case "cutcategory":             require_once ("category.cut.php"); break;
            case "movecategory":            require_once ("category.move.php"); break;
            case "showcategory":            require_once ("category.showstructure.php"); break;
            // glossary
            case 'glossary':
            case 'saveglossary':
            case 'updateglossary':
            case 'deleteglossary':          require_once('glossary.main.php'); break;
            case 'addglossary':             require_once('glossary.add.php'); break;
            case 'editglossary':            require_once('glossary.edit.php'); break;
            // functions for cookie administration
            case "setcookie":                require_once ("cookie.check.php"); break;
            case "cookies":                  require_once ("cookie.check.php"); break;
            case "delcookie":                require_once ("cookie.check.php"); break;
            // adminlog administration
            case 'adminlog':
            case 'deleteadminlog':          require_once ('adminlog.php'); break;
            // functions for password administration
            case "passwd":                  require_once ("pwd.change.php"); break;
            case "savepwd":                 require_once ("pwd.save.php"); break;
            // functions for session administration
            case "viewsessions":            require_once ("stat.main.php"); break;
            case "sessionbrowse":           require_once ("stat.browser.php"); break;
            case "sessionsearch":           require_once ("stat.query.php"); break;
            case "sessionsuche":            require_once ("stat.form.php"); break;
            case "viewsession":             require_once ("stat.show.php"); break;
            case "statistics":              require_once ("stat.ratings.php"); break;
            // functions for config administration
            case 'config':                  require_once ("configuration.php"); break;
            case 'linkconfig':              require_once ('linkconfig.main.php'); break;
            // functions for backup administration
            case 'backup':                  require_once ('backup.main.php'); break;
            case 'restore':                 require_once ('backup.import.php'); break;
            // functions for FAQ export
            case "export":                  require_once ("export.main.php"); break;
            case 'plugins':                 require_once ('plugins.main.php'); break;
            case 'firefoxsearch':           require_once ('plugins.firefoxsearch.php'); break;
            case 'msiesearch':              require_once ('plugins.msiesearch.php'); break;

            default:                        print "Error"; break;
        }
    } else {
        // start page with some informations about the FAQ
        print '<h2>phpMyFAQ Information</h2>';
        $PMF_TABLE_INFO = $db->getTableStatus();
?>
    <div id="quicklinks">
    <fieldset>
        <legend><?php print $PMF_LANG['ad_quicklinks']; ?></legend>
        <ul>
<?php
        addMenuEntry('addcateg,editcateg,delcateg',     'addcategory',                  'ad_quick_category');
        addMenuEntry('addbt',                           'editentry',                    'ad_quick_record');
        addMenuEntry('adduser,edituser,deluser',        'user&amp;user_action=add',     'ad_quick_user');
        if ($groupSupport) {
            addMenuEntry('adduser,edituser,deluser',    'group&amp;group_action=add',   'ad_quick_group');
        }
?>
        </ul>
    </fieldset>
    </div>

    <dl class="table-display">
        <dt><strong><?php print $PMF_LANG["ad_start_visits"]; ?></strong></dt>
        <dd><?php print $PMF_TABLE_INFO[SQLPREFIX."faqsessions"]; ?></dd>
        <dt><strong><?php print $PMF_LANG["ad_start_articles"]; ?></strong></dt>
        <dd><?php print $PMF_TABLE_INFO[SQLPREFIX."faqdata"]; ?></dd>
        <dt><strong><?php print $PMF_LANG["ad_start_comments"]; ?></strong></dt>
        <dd><?php print $PMF_TABLE_INFO[SQLPREFIX."faqcomments"]; ?></dd>
        <dt><strong><?php print $PMF_LANG["msgOpenQuestions"]; ?></strong></dt>
        <dd><?php print $PMF_TABLE_INFO[SQLPREFIX."faqquestions"]; ?></dd>
    </dl>
<?php
        $rg = @ini_get("register_globals");
        if ($rg == "1") {
            $rg = "on";
        } else {
            $rg = "off";
        }

        $sm = @ini_get("safe_mode");
        if ($sm == "1") {
            $sm = "on";
        } else {
            $sm = "off";
        }
?>
    <h2>System Information</h2>
    <dl class="table-display">
        <dt><strong>phpMyFAQ Version</strong></dt>
        <dd>phpMyFAQ <?php print $PMF_CONF["version"]; ?></dd>
        <dt><strong>Server Software</strong></dt>
        <dd><?php print $_SERVER["SERVER_SOFTWARE"]; ?></dd>
        <dt><strong>PHP Version</strong></dt>
        <dd>PHP <?php print phpversion(); ?></dd>
        <dt><strong>Register Globals</strong></dt>
        <dd><?php print $rg; ?></dd>
        <dt><strong>Safe Mode</strong></dt>
        <dd><?php print $sm; ?></dd>
        <dt><strong>Database Client Version</strong></dt>
        <dd><?php print $db->client_version(); ?></dd>
        <dt><strong>Database Server Version</strong></dt>
        <dd><?php print $db->server_version(); ?></dd>
        <dt><strong>Webserver Interface</strong></dt>
        <dd><?php print strtoupper(@php_sapi_name()); ?></dd>
    </dl>
    <h2>Online Version Information</h2>
<?php
        if (isset($_POST["param"]) && $_POST["param"] == "version") {
            require_once (PMF_ROOT_DIR."/inc/libs/xmlrpc.php");
            $param = $_POST["param"];
            $xmlrpc = new xmlrpc_client("/xml/version.php", "www.phpmyfaq.de", 80);
            $msg = new xmlrpcmsg("phpmyfaq.version", array(new xmlrpcval($param, "string")));
            $answer = $xmlrpc->send($msg);
            $result = $answer->value();
            if ($answer->faultCode()) {
                print "<p>Error: ".$answer->faultCode()." (" .htmlspecialchars($answer->faultString()).")</p>";
            } else {
                printf('<p>%s <a href="http://www.phpmyfaq.de" target="_blank">www.phpmyfaq.de</a>: <strong>phpMyFAQ %s</strong>', $PMF_LANG['ad_xmlrpc_latest'], $result->scalarval());
                // Installed phpMyFAQ version is outdated
                if (-1 == version_compare($PMF_CONF["version"], $result->scalarval())) {
                    print '<br />'.$PMF_LANG['ad_you_should_update'];
                }
                print '</p>';
            }
        } else {
?>
    <form action="index.php" method="post">
    <input type="hidden" name="param" value="version" />
    <input class="submit" type="submit" value="<?php print $PMF_LANG["ad_xmlrpc_button"]; ?>" />
    </form>
<?php
        }
    }
// User is NOT authenticated
} else {
?>
    <form action="index.php" method="post">
    <fieldset class="login">
        <legend class="login">phpMyFAQ Login</legend>
<?php
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout") {
        print "<p>".$PMF_LANG["ad_logout"]."</p>";
    }
    if (isset($error)) {
        print "<p><strong>".$error."</strong></p>\n";
    } else {
        print "<p><strong>".$PMF_LANG["ad_auth_insert"]."</strong></p>\n";
    }
?>
        <label class="left" for="faqusername"><?php print $PMF_LANG["ad_auth_user"]; ?></label>
        <input type="text" name="faqusername" id="faqusername" size="20" /><br />

        <label class="left" for="faqpassword"><?php print $PMF_LANG["ad_auth_passwd"]; ?></label>
        <input type="password" size="20" name="faqpassword" id="faqpassword" /><br />

        <input class="submit" style="margin-left: 190px;" type="submit" value="<?php print $PMF_LANG["ad_auth_ok"]; ?>" />
        <input class="submit" type="reset" value="<?php print $PMF_LANG["ad_auth_reset"]; ?>" />

        <p><img src="images/arrow.gif" width="11" height="11" alt="<?php print $PMF_LANG["lostPassword"]; ?>" border="0" /> <a href="password.php" title="<?php print $PMF_LANG["lostPassword"]; ?>">
<?php print $PMF_LANG["lostPassword"]; ?>
</a></p>
        <p><img src="images/arrow.gif" width="11" height="11" alt="<?php print PMF_htmlentities($PMF_CONF["title"]); ?>" border="0" /> <a href="../index.php" title="<?php print PMF_htmlentities($PMF_CONF["title"]); ?>"><?php print PMF_htmlentities($PMF_CONF["title"]); ?></a></p>
    </fieldset>
    </form>
<?php
}

if (DEBUG) {
    print "\n";
    print '<div id="debug_main">DEBUG INFORMATION:<br />'.$db->sqllog().'</div>';
    $cookies = '';
    foreach($_COOKIE as $key => $value) {
        $cookies .= $key.': '.$value.'<br />';
    }
    print "\n";
    print '<div id="debug_cookies">COOKIES:<br />'.$cookies.'</div>';
    print "\n<br />";
    print '<div id="debug_tables">TABLES &amp; RECORDS:<br />';
    $tableStatuses = $db->getTableStatus();
    foreach ($tableStatuses as $key => $value) {
        print "$key: $value<br />";
    }
    print '</div>';
}

require_once('footer.php');

$db->dbclose();
