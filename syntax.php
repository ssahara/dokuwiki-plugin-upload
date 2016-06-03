<?php
/**
 * Upload plugin, allows upload for users with correct
 * permission fromin a wikipage to a defined namespace.
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Christian Moll <christian@chrmoll.de>
 * @author    Franz Häfner <fhaefner@informatik.tu-cottbus.de>
 * @author    Randolf Rotta <rrotta@informatik.tu-cottbus.de>
 */

if(!defined('DOKU_INC')) die();

class syntax_plugin_upload extends DokuWiki_Syntax_Plugin {

    function getType()  { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort()  { return 32; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{upload>.+?\}\}', $mode, 'plugin_upload');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;

        $match = substr($match, 9, -2);
        list($target, $option) = explode('|', $match, 2);

        if (strpos($option, 'OVERWRITE') !== false) $options['overwrite'] = 1;
        if (strpos($option, 'RENAMEABLE') !== false) $options['renameable'] = 1;

        $ns = trim($target);
        switch ($ns) {
            case '@page@':
                $ns = $ID;
                break;
            case '@current@':
                $ns = getNS($ID);
                break;
            default:
                resolve_pageid(getNS($ID), $ns, $exists);
        }

        return array($state, $ns, $options);
    }

    function render($format, Doku_Renderer $renderer, $data) {
        list($state, $uploadns, $options) = $data;

        if($format == 'xhtml') {
            //check auth
            $auth = auth_quickaclcheck($uploadns . ':*');

            if ($auth >= AUTH_UPLOAD) {
                $renderer->doc .= $this->_uploadform($uploadns, $auth, $options);
//				$renderer->info['cache'] = false;
            }
            return true;
        } elseif ($format == 'metadata') {
            $renderer->meta['has_upload_form'] = $uploadns . ':*';
            return true;
        }
        return false;
    }

    /**
     * Print the media upload form if permissions are correct
     *
     * @author Christian Moll <christian@chrmoll.de>
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author    Franz Häfner <fhaefner@informatik.tu-cottbus.de>
     * @author    Randolf Rotta <rrotta@informatik.tu-cottbus.de>
     */
    protected function _uploadform($ns, $auth, $options) {
        global $ID, $lang;
        $html = '';

        if ($auth < AUTH_UPLOAD) return;

        $params = array();
        $params['id'] = 'upload_plugin';
        $params['action'] = wl($ID);
        $params['method'] = 'post';
        $params['enctype'] = 'multipart/form-data';
        $params['class'] = 'upload__plugin';

        // Modification of the default dw HTML upload form
        $form = new Doku_Form($params);
        $form->startFieldset($lang['fileupload']);
        $form->addElement(formSecurityToken());
        $form->addHidden('page', hsc($ID));
        $form->addHidden('ns', hsc($ns));
        $form->addElement(form_makeFileField('upload', $lang['txt_upload'] . ':', 'upload__file'));
        if ($options['renameable']) {
            // don't name this field here "id" because it is misinterpreted by DokuWiki if the upload form is not in media manager
            $form->addElement(form_makeTextField('new_name', '', $lang['txt_filename'] . ':', 'upload__name'));
        }

        if ($auth >= AUTH_DELETE) {
            if ($options['overwrite']) {
                //$form->addElement(form_makeCheckboxField('ow', 1, $lang['txt_overwrt'], 'dw__ow', 'check'));
                // circumvent wrong formatting in doku_form
                $form->addElement(
                    '<label class="check" for="dw__ow">' .
                    '<span>' . $lang['txt_overwrt'] . '</span>' .
                    '<input type="checkbox" id="dw__ow" name="ow" value="1"/>' .
                    '</label>'
                );
            }
        }
        $form->addElement(form_makeButton('submit', '', $lang['btn_upload']));
        $form->endFieldset();

        $html .= '<div class="upload_plugin">' . NL;
        $html .= $form->getForm();
        $html .= '</div>' . NL;
        return $html;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
