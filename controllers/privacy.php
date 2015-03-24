<?php

/**
 * EXHIBIT A. Common Public Attribution License Version 1.0
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the “License”);
 * you may not use this file except in compliance with the License. You may obtain a copy of the License at
 * http://www.oxwall.org/license. The License is based on the Mozilla Public License Version 1.1
 * but Sections 14 and 15 have been added to cover use of software over a computer network and provide for
 * limited attribution for the Original Developer. In addition, Exhibit A has been modified to be consistent
 * with Exhibit B. Software distributed under the License is distributed on an “AS IS” basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for the specific language
 * governing rights and limitations under the License. The Original Code is Oxwall software.
 * The Initial Developer of the Original Code is Oxwall Foundation (http://www.oxwall.org/foundation).
 * All portions of the code written by Oxwall Foundation are Copyright (c) 2011. All Rights Reserved.

 * EXHIBIT B. Attribution Information
 * Attribution Copyright Notice: Copyright 2011 Oxwall Foundation. All rights reserved.
 * Attribution Phrase (not exceeding 10 words): Powered by Oxwall community software
 * Attribution URL: http://www.oxwall.org/
 * Graphic Image as provided in the Covered Code.
 * Display of Attribution Information is required in Larger Works which are defined in the CPAL as a work
 * which combines Covered Code or portions thereof with code not governed by the terms of the CPAL.
 */

/**
 * @author Podyachev Evgeny <joker.OW2@gmail.com>
 * @package ow_plugins.privacy.controllers
 * @since 1.0
 */
class PRIVACY_CTRL_Privacy extends OW_ActionController
{
    private $actionService;
    private $userService;

    public function __construct()
    {
        parent::__construct();

        $this->actionService = PRIVACY_BOL_ActionService::getInstance();
        $this->userService = BOL_UserService::getInstance();
    }
    
    public function index( $params )
    {
        $userId = OW::getUser()->getId();

        if ( OW::getRequest()->isAjax() )
        {
            exit;
        }
        
        if ( !OW::getUser()->isAuthenticated() || $userId === null )
        {
            throw new AuthenticateException();
        }

        $contentMenu = new BASE_CMP_PreferenceContentMenu();
        $contentMenu->getElement('privacy')->setActive(true);

        $this->addComponent('contentMenu', $contentMenu);

        $language = OW::getLanguage();

        $this->setPageHeading($language->text('privacy', 'privacy_index'));
        $this->setPageHeadingIconClass('ow_ic_lock');

        // -- Action form --
        
        $privacyForm = new Form('privacyForm');
        $privacyForm->setId('privacyForm');

        $actionSubmit = new Submit('privacySubmit');
        $actionSubmit->addAttribute('class', 'ow_button ow_ic_save');

        $actionSubmit->setValue($language->text('privacy', 'privacy_submit_button'));
        
        $privacyForm->addElement($actionSubmit);

        // --
        
        $actionList = PRIVACY_BOL_ActionService::getInstance()->findAllAction();

        $actionNameList = array();
        foreach( $actionList as $action )
        {
            $actionNameList[$action->key] = $action->key;
        }

        $actionValueList = PRIVACY_BOL_ActionService::getInstance()->getActionValueList($actionNameList, $userId);

        $actionValuesEvent= new BASE_CLASS_EventCollector( PRIVACY_BOL_ActionService::EVENT_GET_PRIVACY_LIST );
        OW::getEventManager()->trigger($actionValuesEvent);
        $data = $actionValuesEvent->getData();
        
        $actionValuesInfo = empty($data) ? array() : $data;
        usort($actionValuesInfo, array($this, "sortPrivacyOptions"));
        
        $optionsList = array();
        // -- sort action values
        foreach( $actionValuesInfo as $value )
        {
            $optionsList[$value['key']] = $value['label'];
        }
        // --

        $resultList = array();

        foreach( $actionList as $action )
        {

            /* @var $action PRIVACY_CLASS_Action */
            if ( !empty( $action->label ) )
            {
                $formElement = new Selectbox($action->key);
                $formElement->setLabel($action->label);
                
                $formElement->setDescription('');

                if ( !empty($action->description) )
                {
                    $formElement->setDescription($action->description);
                }

                $formElement->setOptions($optionsList);
                $formElement->setHasInvitation(false);

                if ( !empty($actionValueList[$action->key]) )
                {
                    $formElement->setValue($actionValueList[$action->key]);
                    
                    if( array_key_exists($actionValueList[$action->key], $optionsList) )
                    {
                        $formElement->setValue($actionValueList[$action->key]);
                    }
                    else if ( $actionValueList[$action->key] != 'everybody' )
                    {
                        $formElement->setValue('only_for_me');
                    }
                }

                $privacyForm->addElement($formElement);

                $resultList[$action->key] = $action->key;
            }
        }

        if ( OW::getRequest()->isPost() )
        {
            if( $privacyForm->isValid($_POST) )
            {
                $values = $privacyForm->getValues();
                $restul = PRIVACY_BOL_ActionService::getInstance()->saveActionValues($values, $userId);

                if ( $restul )
                {
                    OW::getFeedback()->info($language->text('privacy', 'action_action_data_was_saved'));
                }
                else
                {
                    OW::getFeedback()->warning($language->text('privacy', 'action_action_data_not_changed'));
                }
                
                $this->redirect();
            }
        }

        
        $this->addForm($privacyForm);
        $this->assign('actionList', $resultList);
    }
    
    private function sortPrivacyOptions( $a, $b )
    {
        if ( $a["sortOrder"] == $b["sortOrder"]  )
        {
            return 0;
        }
        
        return $a["sortOrder"] < $b["sortOrder"] ? -1 : 1;
    }

    public function noPermission( $params )
    {
        $username = $params['username'];

        $user = BOL_UserService::getInstance()->findByUsername($username);
        
        if ( $user === null )
        {
            throw new Redirect404Exception();
        }

        $this->setPageHeading(OW::getLanguage()->text('privacy', 'privacy_no_permission_heading'));
        $this->setPageHeadingIconClass('ow_ic_lock');

        if( OW::getSession()->isKeySet('privacyRedirectExceptionMessage') )
        {
            $this->assign('message', OW::getSession()->get('privacyRedirectExceptionMessage'));
        }

        $avatarService = BOL_AvatarService::getInstance();

        $viewerId = OW::getUser()->getId();

        $userId = $user->id;

        $this->assign('owner', false);

        $avatar = $avatarService->getAvatarUrl($userId, 2);
        $this->assign('avatar', $avatar ? $avatar : $avatarService->getDefaultAvatarUrl(2));
        $roles = BOL_AuthorizationService::getInstance()->getRoleListOfUsers(array($userId));
        $this->assign('role', !empty($roles[$userId]) ? $roles[$userId] : null);

        $userService = BOL_UserService::getInstance();

        $this->assign('username', $username);

        $this->assign('avatarSize', OW::getConfig()->getValue('base', 'avatar_big_size'));
    }
}