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

    public function check_login() {
        return !empty($this->keyword);
    }

    public function search($searchtext, $page = 0) {
        global $SESSION;

        $sesskeyword    = 'planetestream_' . $this->id . '_keyword';
        $sesssort       = 'planetestream_' . $this->id . '_sort';
        $sesscat        = 'planetestream_' . $this->id . '_category';
        $sessshow       = 'planetestream_' . $this->id . '_mediatype';
        $sesschapters   = 'planetestream_' . $this->id . '_chapters';
        $sort           = optional_param('planetestream_sort', null, PARAM_TEXT);
        $cat            = optional_param('planetestream_cat', null, PARAM_TEXT);
        $show           = optional_param('planetestream_show', null, PARAM_TEXT);

        if (isset($_POST['planetestream_chapters'])) {
            $chapters = 'on';
        } else {
            $chapters = false;
        }

        if ($page && !$searchtext && isset($SESSION->{$sesskeyword})) {
            $searchtext = $SESSION->{$sesskeyword};
        }
        if ($page && !$sort && isset($SESSION->{$sesssort})) {
            $sort = $SESSION->{$sesssort};
        }
        if (!$sort) {
            $sort = 'relevance'; // Default.
        }
        if ($page && !$cat && isset($SESSION->{$sesscat})) {
            $cat = $SESSION->{$sesscat};
        }

        if ($page && !$show && isset($SESSION->{$sessshow})) {
            $show = $SESSION->{$sessshow};
        }

        if ($page && !$chapters && isset($SESSION->{$sesschapters})) {
            $chapters = $SESSION->{$sesschapters};
        }

        $SESSION->{$sesskeyword}   = $searchtext;
        $SESSION->{$sesssort}      = $sort;
        $SESSION->{$sesscat}       = (string) $cat;
        $SESSION->{$sessshow}      = (string) $show;
        $SESSION->{$sesschapters}  = (string) $chapters;

        $ret            = array();
        $ret['nologin'] = true;
        $ret['page']    = (int) $page;

        if ($ret['page'] < 1) {
            $ret['page'] = 1;
        }

        if ($searchtext == '') {
            $searchtext = '*';
        }

        $ret['list']      = $this->funcgetlist($searchtext, $ret['page'] - 1, $sort, $cat, $show, $chapters);

        $ret['norefresh'] = true;
        $ret['nosearch']  = true;
        $ret['pages']     = -1;

        if (count($ret['list']) < 10) {
            $ret['pages'] = 0;
            $ret['page']  = 0;
        }

        return $ret;

    }

    private function funcgetlist($keyword, $pageindex, $sort, $cat, $show, $chapters) {
        global $USER, $SESSION;

        $list = array();

        $this->feed_url = $this->get_url() . '/VLE/Moodle/Default.aspx?search=' . urlencode($keyword) . '&format=5&pageindex=' .
            $pageindex . '&orderby=' . $sort . '&cat=' . $cat . '&show=' . $show . '&delta=' .
            $this->funcobfuscate($USER->username) . '&checksum=' . $this->funcgetchecksum() . '&mc=' . $chapters;

        $c = new curl(array(
            'cache'         => false,
            'module_cache'  => false
        ));
        $content    = $c->get($this->feed_url);
        $xml        = simplexml_load_string($content);

        $dimensions = '';

        if (isset($sessdimensions) && isset($SESSION->{$sessdimensions})) {
            $dimensions = $SESSION->{$sessdimensions};
        }

        foreach ($xml->item as $item) {
            $title       = (string) $item->title;
            $description = (string) $item->description;
            $description = str_replace('[ No Description ]', '', $description);
            $source      = (string) $this->get_url() . '/VLE/Moodle/Video/' . $item->file . '.swf';
            $recordtype  = (string) 'Recording';
            $tumbnailurl = (string) $this->get_url() . '/GetImage.aspx';

            if ($item->recordtype == '2') {
                $recordtype = 'Playlist';
            } else if ($item->recordtype == '4') {
                $recordtype = 'Photoset';
            } else if ($item->recordtype == '-99') {
                $recordtype = 'Chapter';
            }
            $shorttitle = '';

            $thumbnailcontainerheight = (string) '190';
            $idparts = explode('~', $item->file);

            if (count($idparts) == 4) {
                $thumbnailcontainerheight = (string) '90';
                $tumbnailurl .= '?type=chap&width=120&height=90&id='.$idparts[3];
                $shorttitle = $title . ' (Chapter) ' . $description;

            } else {
                $tumbnailurl .= '?type=cd&width=354&height=190&forceoverlay=true&id='.$idparts[0].'~'.$idparts[1];
                $shorttitle = $recordtype . ' ' . get_string('addedon', 'repository_planetestream') . ' ' . date('d/m/Y', (integer)$item->addedat) .
                    ' ' . get_string('addedby', 'repository_planetestream') . ' ' . $item->addedby . ' ' . $title . ' ' . $description;
            }

            $dimensions  = (string) $SESSION->{'planetestream_' . $this->id . '_dimensions'};
            $list[]      = array(
                'shorttitle'        => $shorttitle,
                'thumbnail_title'   => $title,
                'title'             => $title . '.m4v', // Extension required.
                'thumbnail'         => $tumbnailurl,
                'thumbnail_width'   => '600',
                'thumbnail_height'  => $thumbnailcontainerheight,
                'license'           => 'Other',
                'size'              => '',
                'date'              => '',
                'lastmodified'      => '',
                'datecreated'       => $item->addedat,
                'author'            => $item->addedby,
                'dimensions'        => str_replace('?d=', '', $dimensions),
                'source'            => $source . $dimensions
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
        $decchecksum = (float)(date('d') + date('m')) + (date('m') * date('d')) + (date('Y') * date('d'));
        $decchecksum += $decchecksum * (date('d') * 2.27409) * .689274;
        return md5(floor($decchecksum));
    }

    private function funcobfuscate($strx) {
        $strbase64chars = '0123456789aAbBcCDdEeFfgGHhiIJjKklLmMNnoOpPQqRrsSTtuUvVwWXxyYZz/+=';
        $strbase64string = base64_encode($strx);
        if ($strbase64string == '') {
            return '';
        }
        $strobfuscated = '';
        for ($i = 0; $i < strlen($strbase64string); $i++) {
            $intpos = strpos($strbase64chars, substr($strbase64string, $i, 1));
            if ($intpos == -1) {
                return '';
            }
            $intpos += strlen($strbase64string) + $i;
            $intpos = $intpos % strlen($strbase64chars);
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
        $ret = array();
        // Help.
        $help = new stdClass();
        $help->type  = 'hidden';
        $help->label = '<div style="position: relative; padding-bottom: 100px; margin-top: -100px;">
                <div style="position: absolute; left: 400px; width: 50px; text-align: center;">
                    <a title="view help" onclick="window.open(\'' . $this->get_url() . '/VLE/Moodle/Help.aspx\'); return false;" href="#">
                    <img style="border-width: 0px; height: 24px; width: 24px;" alt="view help" src="/repository/planetestream/pix/help.png"><br />help</a>
                </div>
                <div style="position: absolute; left: 450px; width: 50px; text-align: center;">
                    <a title="add media" onclick="window.open(\'' . $this->get_url() . '/UploadContentVLE.aspx?sourceID=11\', \'add\', \'width=720,height=680,left=100,top=100\'); return false;" href="#">
                    <img style="border-width: 0px; height: 24px; width: 24px;" alt="add media" src="/repository/planetestream/pix/addmedia.png"><br />add media</a>
                </div>
            </div>';

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

        $c = new curl(array(
            'cache'         => false,
            'module_cache'  => false
        ));

        $c->connecttimeout  = 6;
        $c->timeout         = 12;

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
        $category->id       = 'planetestream_cat';
        $category->name     = 'planetestream_cat';
        $category->options  = $cats;

        $chapters           = new stdClass();
        $chapters->type     = 'checkbox';
        $chapters->id       = 'planetestream_chapters';
        $chapters->name     = 'planetestream_chapters';
        $chapters->label    = get_string('sort_includechapters', 'repository_planetestream') . ': ';

        $ret['login']       = array(
            $help,
            $search,
            $show,
            $sort,
            $category,
            $chapters
        );
        $ret['login_btn_label']  = get_string('search');
        $ret['login_btn_action'] = 'search';
        $ret['allowcaching']     = false;

        $strdimensions = $xml->dimensions[0];
        if ($strdimensions != '') {
            $SESSION->{'planetestream_' . $this->id . '_dimensions'} = '?d=' . $strdimensions;
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

        $mform->addElement('static', null, '', get_string('settings_config', 'repository_planetestream'));
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
            $intpos = strpos($url, '/', $intpos + 3);
            if ($intpos != 0) {
                $url = substr($url, 0, $intpos);
            }
        }
        return $url;
    }

}