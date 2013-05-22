<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Planet eStream v5 Repository Plugin
 *
 * @since       2.0
 * @package     repository_planetestream
 * @copyright   2012 Planet eStream
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once($CFG->dirroot . '/config.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_login();

class repository_planetestream extends repository {

    // public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
    //    parent::__construct($repositoryid, $context, $options);
    // }

    public function check_login() {
        return !empty($this->keyword);
    }

    public function search($search_text, $page = 0) {
        global $SESSION;

        $sort           = optional_param('planetestream_sort', '', PARAM_TEXT);
        $sess_keyword   = 'planetestream_' . $this->id . '_keyword';
        $sess_sort      = 'planetestream_' . $this->id . '_sort';
        $sess_cat       = 'planetestream_' . $this->id . '_category';
        $sess_show      = 'planetestream_' . $this->id . '_mediatype';
        $cat            = optional_param('planetestream_cat', '', PARAM_TEXT);
        $show           = optional_param('planetestream_show', '', PARAM_TEXT);

        if ($page && !$search_text && isset($SESSION->{$sess_keyword})) {
            $search_text = $SESSION->{$sess_keyword};
        }
        if ($page && !$sort && isset($SESSION->{$sess_sort})) {
            $sort = $SESSION->{$sess_sort};
        }
        if (!$sort) {
            $sort = 'relevance'; // Default.
        }
        if ($page && !$cat && isset($SESSION->{$sess_cat})) {
            $cat = $SESSION->{$sess_cat};
        }
        if ($page && !$show && isset($SESSION->{$sess_show})) {
            $show = $SESSION->{$sess_show};
        }

        // Save search in session.
        $SESSION->{$sess_keyword} = $search_text;
        $SESSION->{$sess_sort}    = $sort;
        $SESSION->{$sess_cat}     = (string) $cat;
        $SESSION->{$sess_show}    = (string) $show;

        $ret            = array();
        $ret['nologin'] = true;
        $ret['page']    = (int) $page;

        if ($ret['page'] < 1) {
            $ret['page'] = 1;
        }

        if ($search_text=='') {
            $search_text='*';
        }

        $ret['list']      = $this->funcgetlist($search_text, $ret['page']-1, $sort, $cat, $show);

        $ret['norefresh'] = true;
        $ret['nosearch']  = true;
        $ret['pages']     = -1;
        if (count($ret['list']) < 10) {
            $ret['pages'] = 0;
            $ret['page']  = 0;
        }

        return $ret;

    }

    private function funcgetlist($keyword, $pageindex, $sort, $cat, $show) {
        global $USER, $SESSION;

        $list = array();

        $this->feed_url = $this->get_url() . '/VLE/Moodle/Default.aspx?search=' . urlencode($keyword) . '&format=5&pageindex=' .
            $pageindex . '&orderby=' . $sort . '&cat=' . $cat . '&show=' . $show . '&delta=' .
            $this->funcobfuscate($USER->username) . '&checksum=' . $this->funcgetchecksum();

        $c = new curl(array(
            'cache' => false,
            'module_cache' => false
        ));
        $content    = $c->get($this->feed_url);
        $xml        = simplexml_load_string($content);

        $dimensions = '';

        if (isset($SESSION->{$sess_dimensions})) {
            $dimensions = $SESSION->{$sess_dimensions};
        }

        foreach ($xml->item as $item) {
            $title       = (string) $item->title;
            $description = (string) $item->description;
            $source      = (string) $this->get_url() . '/VLE/Moodle/Video/' . $item->file . '.swf';
            $recordtype  = (string) 'Recording';

            if ($item->recordtype=='2') {
                $recordtype = 'Playlist';
            } else {
                if ($item->recordtype=='4') {
                    $recordtype = 'Photoset';
                }
            }

            $shorttitle  = (string) '<div style="background-color: #fafafa; padding: 2px 12px 2px 12px; font-size: 12px; margin-top: -2px; text-align: left;" onmouseover="var el=document.getElementsByTagName(\'div\');for(var i=0;i<el.length;i++){if(el[i].getAttribute(\'class\')!=undefined){if(el[i].getAttribute(\'class\').indexOf(\'fp-filename-field\')==0){el[i].setAttribute(\'style\', \'z-index:\'+[9000-i]+\';\');}}}">';
            $shorttitle .= '
            <div style="margin-left: 120px; font-weight: bold;">' . $title . '</div>
            <div style="margin: 1px 0 0 120px;">'
                . '<span style="font-style: italic;">'
                . $recordtype . '</span> '
                . get_string('addedon', 'repository_planetestream') . ' '
                . date('d/m/Y', (integer)$item->addedat) . ' '
                . get_string('addedby', 'repository_planetestream') . ' '
                . $item->addedby . '
            </div>
            <div style="margin-top: 6px; line-height: 18px; text-align: justify;">' . $description . '</div>';
            $chapters = (string) '';
            $strbgcol = (string) '#fafafa';
            foreach ($item->chapters->chapter as $chapter) {
                $chapters .= '<tr style="background-color: '. $strbgcol . '">';
                $chapters .= '<td style="width: 122px; text-align: center;"><img src="' . $this->get_url() . '/GetImage.aspx?type=chap&width=108&height=81&overlay=true&id=' . $chapter->id . '"></td>';
                $chapters .= '<td style="line-height: 18px; text-align: justify;">' . $chapter->title . '</td>';
                $chapters .= '<td style="width: 28px; text-align: center;"><a href="#" style="text-decoration: underline" onclick="var el=document.getElementsByTagName(\'input\');for(var i=0;i<el.length;i++){if(el[i].id.indexOf(\'filesource-\')==0){window.pesx=el[i].id;setTimeout(\'document.getElementById(window.pesx).value=document.getElementById(window.pesx).value.replace(\\\'.swf\\\',\\\'~' . $chapter->id . '.swf\\\')\',110);} }; return false;">add</a></td>';
                $chapters .= '</tr>';
                if ($strbgcol == '#fafafa') {
                    $strbgcol = '#fbfbfb';
                } else {
                    $strbgcol = '#fafafa';
                }
            }

            if ($chapters!='') {
                $shorttitle .= '<table style="border-collapse: separate; border-spacing: 1px;"><tr><td colspan="3" style="font-weight: bold;">Chapters</td></tr>' . $chapters . '</table>';
            }
            $shorttitle .= '</div>';
            $idparts     = explode('~', $item->file);
            $id      = (string) $idparts[0].'~'.$idparts[1];
            $dimensions  = (string) $SESSION->{'planetestream_' . $this->id . '_dimensions'};
            $list[]      = array(
                'shorttitle' => $shorttitle,
                'thumbnail_title' => (string) $title,
                'title' => (string) $title . '.m4v', // Extension required.
                'thumbnail' => (string) $this->get_url() . '/GetImage.aspx?type=cd&width=354&height=190&forceoverlay=true&id=' . $id,
                'thumbnail_width' => '600',
                'thumbnail_height' => '190',
                'license' => 'Other',
                'size' => '',
                'date' => '',
                'lastmodified' => '',
                'datecreated' => $item->addedat,
                'author' => (string) $item->addedby,
                'dimensions' => str_replace('?d=', '', $dimensions),
                'source' => $source . $dimensions
            );
        }
        return $list;
    }

    /**
     * planetestream plugin doesn't support global search
     */
    public function global_search() {
        return false;
    }

    private function funcgetchecksum() {
        $decchecksum = (float)(date('d')+date('m'))+(date('m')*date('d'))+(date('Y')*date('d'));
        $decchecksum += $decchecksum*(date('d')*2.27409)*.689274;
        return md5(floor($decchecksum));
    }

    private function funcobfuscate($strx) {
        $strbase64chars = '0123456789aAbBcCDdEeFfgGHhiIJjKklLmMNnoOpPQqRrsSTtuUvVwWXxyYZz/+=';
        $strbase64string = base64_encode($strx);
        if ($strbase64string=='') {
            return '';
        }
        $strobfuscated = '';
        for ($i=0; $i<strlen($strbase64string); $i++) {
            $intpos = strpos($strbase64chars, substr($strbase64string, $i, 1));
            if ($intpos==-1) {
                return '';
            }
            $intpos += strlen($strbase64string) + $i;
            $intpos = $intpos%strlen($strbase64chars);
            $strobfuscated .= substr($strbase64chars, $intpos, 1);
        }

        return $strobfuscated;
    }

    /**
     * get_listing.
     *
     * @param string $path
     * @param int $page
     * @return array
     */
    public function get_listing($path = '', $page = '') {
        return array();
    }

    /**
     * Generate search form
     */
    public function print_login($ajax = true) {
        global $USER, $SESSION;

        $ret         = array();
        // Help.
        $help        = new stdClass();
        $help->type  = 'hidden';
        $help->label = '<div style="position: relative; padding-bottom: 100px; margin-top: -100px;">
            <div style="position: absolute; left: 400px; text-align: right;">
            <a title="view help" onclick="window.open(\'' . $this->get_url() . '/VLE/Moodle/Help.aspx\'); return false;" href="#">
            <img style="border-width: 0px; height: 24px; width: 24px;" title="view help" src="/repository/planetestream/pix/help.png"></a>
            </div></div>';

        // Searchbox.
        $search        = new stdClass();
        $search->type  = 'text';
        $search->id    = 'planetestream_search';
        $search->name  = 's';
        $search->label = get_string('search', 'repository_planetestream') . ': ';

        // Media type.
        $show          = new stdClass();
        $show->type    = 'select';
        $show->options = array(
            (object) array(
                'value' => '0',
                'label' => get_string('show_all', 'repository_planetestream')
            ),
            (object) array(
                'value' => '7',
                'label' => get_string('show_video', 'repository_planetestream')
            ),
            (object) array(
                'value' => '2',
                'label' => get_string('show_playlist', 'repository_planetestream')
            ),
            (object) array(
                'value' => '4',
                'label' => get_string('show_photoset', 'repository_planetestream')
            ),
        );
        $show->id      = 'planetestream_show';
        $show->name    = 'planetestream_show';
        $show->label   = get_string('show', 'repository_planetestream') . ': ';

        // Sort cb.
        $sort          = new stdClass();
        $sort->type    = 'select';
        $sort->options = array(
            (object) array(
                'value' => '0',
                'label' => get_string('sort_orderby_relevance', 'repository_planetestream')
            ),
            (object) array(
                'value' => '8',
                'label' => get_string('sort_orderby_date', 'repository_planetestream')
            ),
            (object) array(
                'value' => '7',
                'label' => get_string('sort_orderby_rating', 'repository_planetestream')
            ),
            (object) array(
                'value' => '3',
                'label' => get_string('sort_orderby_popularity', 'repository_planetestream')
            )
        );
        $sort->id      = 'planetestream_sort';
        $sort->name    = 'planetestream_sort';
        $sort->label   = get_string('sort_orderby', 'repository_planetestream') . ': ';

        // Category cb.
        $category           = new stdClass();
        $category->type     = 'select';
        $url           = (string) $this->get_url() . '/VLE/Moodle/Default.aspx?show=info&delta=' . $this->funcobfuscate($USER->username) . '&checksum=' . $this->funcgetchecksum();

        $c             = new curl(array(
            'cache' => false,
            'module_cache' => false
        ));

        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($c, CURLOPT_TIMEOUT, 12);
        $content       = $c->get($url);
        $xml           = simplexml_load_string($content);
        $cats          = array();
        $cats[]        = array(
            'value' => '0',
            'label' => 'All'
        );
        foreach ($xml->cats->cat as $catitem) {
            $cats[] = array(
                'value' => (string) $catitem->id,
                'label' => (string) $catitem->name
            );
        }
        if ($xml->catname[0] != '') {
            $category->label = $xml->catname[0] . ':';
        } else {
            $category->label = 'Category:';
        }

        $category->id            = 'planetestream_cat';
        $category->name          = 'planetestream_cat';
        $category->options       = $cats;

        $ret['login']            = array(
            $help,
            $search,
            $show,
            $sort,
            $category
        );
        $ret['login_btn_label']  = get_string('search');
        $ret['login_btn_action'] = 'search';
        $ret['allowcaching']     = false;

        $strdimensions = $xml->dimensions[0];
        if ($strdimensions != '') {
            $SESSION->{'planetestream_' . $this->id . '_dimensions'} = (string) '?d=' . $strdimensions;
        }

        return $ret;
    }

    /**
     * file types supported by planetestream plugin
     * @return array
     */
    public function supported_filetypes() {
        return array(
            'video'
        );
    }

    /**
     * Gets the names of the repository config options as an array
     * @return array The array of config option names
     */
    public static function get_type_option_names() {
        return array(
            'url',
            'pluginname'
        );
    }

    /**
     * Edit/Create Admin Settings Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform, $classname);

        $mform->addElement('text', 'url', get_string('settingsurl', 'repository_planetestream'));
        $mform->setType('url', PARAM_RAW);
        $mform->addRule('url', get_string('required'), 'required', null, 'client');

        $mform->addElement('static', null, '', get_string('settingsurl_text', 'repository_planetestream'));

        $mform->addElement('static', null, '', '<p>&nbsp;</p><p>Please note: The remainder of the configuration options can be found on your Planet eStream Website Administration Console, within the <span style="font-style: italic">VLE Integration section.</span></p>');
    }

    /**
     * planetestream plugin only return external links
     * @return int
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    public function get_url() {
        $url = (string) get_config('planetestream', 'url');
        $intpos = (int) strpos($url, '://');
        if ($intpos != 0) {
            $intpos = strpos($url, '/', $intpos+3);
            if ($intpos != 0) {
                $url = substr($url, 0, $intpos);
            }
        }
        return $url;
    }

}