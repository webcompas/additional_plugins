<?php # $Id: serendipity_event_adduser.php,v 1.26 2010/02/25 09:13:08 garvinhicking Exp $

include_once dirname(__FILE__) . '/common.inc.php';

class serendipity_event_adduser extends serendipity_event
{
    function introspect(&$propbag)
    {
        global $serendipity;

        $propbag->add('name',        PLUGIN_ADDUSER_NAME);
        $propbag->add('description', PLUGIN_ADDUSER_DESC);
        $propbag->add('stackable',   false);
        $propbag->add('author',      'Garvin Hicking');
        $propbag->add('version',     '2.34');
        $propbag->add('requirements',  array(
            'serendipity' => '0.8',
            'smarty'      => '2.6.7',
            'php'         => '4.1.0'
        ));
        $propbag->add('groups', array('BACKEND_USERMANAGEMENT'));
        $propbag->add('event_hooks', array(
            'frontend_configure'    => true,
            'frontend_display'      => true,
            'entries_header'        => true,
            'entry_display'         => true,
            'frontend_saveComment'  => true,
            'external_plugin'       => true
        ));

        $propbag->add('configuration', array(
            'instructions',
            'registered_only',
            'true_identities'
        ));

        // Register (multiple) dependencies. KEY is the name of the depending plugin. VALUE is a mode of either 'remove' or 'keep'.
        // If the mode 'remove' is set, removing the plugin results in a removal of the depending plugin. 'Keep' meens to
        // not touch the depending plugin.
        $this->dependencies = array('serendipity_plugin_adduser' => 'keep');
    }

    function introspect_config_item($name, &$propbag)
    {
        switch($name) {
            case 'instructions':
                $propbag->add('type',        'html');
                $propbag->add('name',        PLUGIN_ADDUSER_INSTRUCTIONS);
                $propbag->add('description', PLUGIN_ADDUSER_INSTRUCTIONS_DESC);
                $propbag->add('default',     PLUGIN_ADDUSER_INSTRUCTIONS_DEFAULT);
                break;

            case 'registered_only':
                $propbag->add('type',        'boolean');
                $propbag->add('name',        PLUGIN_ADDUSER_REGISTERED_ONLY);
                $propbag->add('description', PLUGIN_ADDUSER_REGISTERED_ONLY_DESC);
                $propbag->add('default',     false);
                break;

            case 'true_identities':
                $propbag->add('type',        'boolean');
                $propbag->add('name',        PLUGIN_ADDUSER_REGISTERED_CHECK);
                $propbag->add('description', PLUGIN_ADDUSER_REGISTERED_CHECK_DESC);
                $propbag->add('default',     true);
                break;

            default:
                    return false;
        }
        return true;
    }

    function generate_content(&$title) {
        $title = PLUGIN_ADDUSER_NAME;
    }

    function event_hook($event, &$bag, &$eventData, $addData = null) {
        global $serendipity;
        static $login_url = null;

        if ($login_url === null) {
            $login_url = $serendipity['baseURL'] . $serendipity['indexFile'] . '?/plugin/loginbox';
        }

        $hooks = &$bag->get('event_hooks');

        if (isset($hooks[$event])) {
            switch($event) {
                case 'frontend_saveComment':
                        if (!isset($serendipity['csuccess'])) {
                            $serendipity['csuccess'] = 'true';
                        }

                        // Check for global emergency moderation
                        if (serendipity_db_bool($this->get_config('registered_only')) && !serendipity_userLoggedIn() && $addData['source2'] != 'adduser') {
                            $eventData = array('allow_comments' => false);
                            $serendipity['messagestack']['comments'][] = PLUGIN_ADDUSER_REGISTERED_ONLY_REASON . 'x';
                            return false;
                        }

                        if (serendipity_db_bool($this->get_config('true_identities')) && !serendipity_userLoggedIn()) {
                            $user = serendipity_db_escape_string(trim($addData['name']));
                            $authors = serendipity_db_query("SELECT authorid FROM {$serendipity['dbPrefix']}authors WHERE realname = '" . $user . "'");
                            if (is_array($authors) && isset($authors[0]['authorid'])) {
                                $eventData = array('allow_comments' => false);
                                $serendipity['messagestack']['comments'][] = sprintf(
                                    PLUGIN_ADDUSER_REGISTERED_CHECK_REASON,

                                    $login_url,
                                    'onclick="javascript:loginbox = window.open(this.href, \'loginbox\', \'width=300,height=300,locationbar=no,menubar=no,personalbar=no,statusbar=yes,status=yes,toolbar=no\'); return false;"'
                                );
                            }
                        }

                    break;

                case 'external_plugin':
                    if ($eventData != 'loginbox') {
                        return true;
                    }


                    $out = array();
                    serendipity_plugin_api::hook_event('backend_login_page', $out);
                    serendipity_smarty_init();
                    $serendipity['smarty']->assign(array(
                        'loginform_add'  => $out,
                        'loginform_url'  => $login_url,
                        'loginform_user' => $_SESSION['serendipityUser'],
                        'loginform_mail' => $_SESSION['serendipityEmail'],
                        'close_window'   => defined('LOGIN_ACTION'),
                        'is_logged_in'   => serendipity_userLoggedIn(),
                        'is_error'       => defined('LOGIN_ERROR')
                    ));
                    $filename = 'loginbox.tpl';
                    $tfile = serendipity_getTemplateFile($filename, 'serendipityPath');
                    if (!$tfile || $tfile == $filename) {
                        $tfile = dirname(__FILE__) . '/' . $filename;
                    }
                    $inclusion = $serendipity['smarty']->security_settings[INCLUDE_ANY];
                    $serendipity['smarty']->security_settings[INCLUDE_ANY] = true;
                    $serendipity['smarty']->display($tfile);

                    break;

                case 'frontend_display':
                    if (serendipity_db_bool($this->get_config('registered_only')) && !serendipity_userLoggedIn()) {
                        $serendipity['messagestack']['comments'][] = sprintf(
                            PLUGIN_ADDUSER_REGISTERED_ONLY_REASON,
                            $serendipity['baseURL'] . $serendipity['indexFile'] . '?serendipity[subpage]=adduser',
                            $serendipity['baseURL'] . 'serendipity_admin.php');
                        $eventData['allow_comments'] = false;
                    }
                    break;

                case 'frontend_configure':
                    if (isset($serendipity['POST']['action']) && isset($serendipity['POST']['user']) && isset($serendipity['POST']['pass'])) {
                        serendipity_login();
                        if (serendipity_userLoggedIn()) {
                            define('LOGIN_ACTION', 'login');
                            header('X-s9y-auth: Login');
                        } else {
                            define('LOGIN_ERROR', true);
                        }
                    } elseif (isset($serendipity['POST']['action']) && isset($serendipity['POST']['logout'])) {
                        serendipity_logout();
                        if (!serendipity_userLoggedIn()) {
                            header('X-s9y-auth: Logout');
                            define('LOGIN_ACTION', 'logout');
                        }
                    }

                    if ((serendipity_db_bool($this->get_config('registered_only')) || serendipity_db_bool($this->get_config('true_identities'))) && $_SESSION['serendipityAuthedUser']) {
                        if (defined('IN_serendipity_admin') && $serendipity['GET']['adminAction'] == 'doEdit') {
                            // void
                        } else {
                            $serendipity['COOKIE']['name']  = (isset($_SESSION['serendipityRealname']) ? $_SESSION['serendipityRealname'] : $_SESSION['serendipityUser']);
                            $serendipity['COOKIE']['email'] = $_SESSION['serendipityEmail'];
                            if ($serendipity['POST']['comment']) {
                                $serendipity['POST']['name']  = $serendipity['COOKIE']['name'];
                                $serendipity['POST']['email'] = $serendipity['COOKIE']['email'];
                            }
                        }
                    }

                    return true;
                    break;

                case 'entry_display':
                    if ($serendipity['GET']['subpage'] == 'adduser' || $serendipity['POST']['subpage'] == 'adduser' || !empty($serendipity['GET']['adduser_activation']) || !empty($this->clean_page)) {
                        if (is_array($eventData)) {
                            $eventData['clean_page'] = true;
                        }
                    }
                    break;

                case 'entries_header':
                    if ($serendipity['GET']['subpage'] == 'adduser' || $serendipity['POST']['subpage'] == 'adduser' || !empty($serendipity['GET']['adduser_activation'])) {
                        $this->clean_page = true;
                        $url      = $serendipity['baseURL'] . $serendipity['indexFile'];
                        $hidden['subpage'] = 'adduser';

                        $username = substr($serendipity['POST']['adduser_user'], 0, 40);
                        $password = substr($serendipity['POST']['adduser_pass'], 0, 32);
                        $email    = $serendipity['POST']['adduser_email'];

                        echo '<div id="adduser_form" style="padding-left: 4px; padding-right: 10px"><a id="adduser"></a>';

                        // Get the config from the sidebar plugin
                        $pair_config = array(
                            'userlevel'       => USERLEVEL_EDITOR,
                            'no_create'       => false,
                            'right_publish'   => false,
                            'instructions'    => $this->get_config('instructions', ''),
                            'usergroups'      => array(),
                            'straight_insert' => false,
                            'approve'         => false,
                            'use_captcha'     => false
                        );
                        $config = serendipity_db_query("SELECT name, value FROM {$serendipity['dbPrefix']}config WHERE name LIKE 'serendipity_plugin_adduser:%'");

                        if (is_array($config)) {
                            foreach($config AS $conf) {
                                $names = explode('/', $conf['name']);
                                if ($names[1] == 'instructions' && !empty($pair_config['instructions'])) {
                                    continue;
                                }

                                if ($names[1] == 'usergroups') {
                                    $ug = (array)explode(',', $conf['value']);

                                    foreach($ug AS $cid) {
                                        if ($cid === false || empty($cid)) {
                                            continue;
                                        }
                                        $pair_config[$names[1]][$cid] = $cid;
                                    }
                                } else {
                                    $pair_config[$names[1]] = serendipity_get_bool($conf['value']);
                                }
                            }
                        }

                        if (!serendipity_common_adduser::adduser($username, $password, $email, $pair_config['userlevel'], $pair_config['usergroups'], $pair_config['no_create'], $pair_config['right_publish'], $pair_config['straight_insert'], $pair_config['approve'], $pair_config['use_captcha'])) {
                            serendipity_common_adduser::loginform($url, $hidden, $pair_config['instructions'], $username, $password, $email, $pair_config['use_captcha']);
                        }

                        echo '</div>';
                    }
                    return true;
                    break;

                default:
                    return false;
            }

        } else {
            return false;
        }
    }
}

/* vim: set sts=4 ts=4 expandtab : */