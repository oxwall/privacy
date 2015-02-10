<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class PRIVACY_CLASS_EventHandler
{

    public function __construct()
    {

    }

    public function addPrivacyPreferenceMenuItem( BASE_CLASS_EventCollector $event )
    {
        $router = OW_Router::getInstance();
        $language = OW::getLanguage();

        $menuItem = new BASE_MenuItem();

        $menuItem->setKey('privacy');
        $menuItem->setLabel($language->text('privacy', 'privacy_index'));
        $menuItem->setIconClass('ow_ic_lock');
        $menuItem->setUrl($router->urlForRoute('privacy_index'));
        $menuItem->setOrder(5);

        $event->add($menuItem);
    }

    public function addConsoleItem( BASE_CLASS_EventCollector $event )
    {
        $event->add(array('label' => OW::getLanguage()->text('privacy', 'privacy_index'), 'url' => OW_Router::getInstance()->urlForRoute('privacy_index')));
    }

    public function addPrivacy( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();
        $params = $event->getParams();

        $event->add(array(
            'key' => 'everybody',
            'label' => $language->text('privacy', 'privacy_everybody'),
            'weight' => 0,
            'sortOrder' => 0
        ));

        $event->add(array(
            'key' => 'only_for_me',
            'label' => $language->text('privacy', 'privacy_only_for_me'),
            'weight' => 10,
            'sortOrder' => 100000
        ));
    }

    public function getActionPrivacy( OW_Event $event )
    {
        $params = $event->getParams();

        if ( empty($params['ownerId']) || empty($params['action']) )
        {
            throw new InvalidArgumentException('Invalid parameters were provided!'); // TODO trow Exeption
        }

        return PRIVACY_BOL_ActionService::getInstance()->getActionValue($params['action'], $params['ownerId']);
    }

    public function getActionMainPrivacyByOwnerIdList( OW_Event $event )
    {
        $params = $event->getParams();

        if ( empty($params['userIdList']) || !is_array($params['userIdList']) || empty($params['action']) )
        {
            throw new InvalidArgumentException('Invalid parameters were provided!'); // TODO trow Exeption
        }

        return PRIVACY_BOL_ActionService::getInstance()->getMainActionValue($params['action'], $params['userIdList']);
    }

    public function removeUserPrivacy( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !empty($params['userId']) )
        {
            PRIVACY_BOL_ActionService::getInstance()->deleteActionDataByUserId((int) $params['userId']);
        }
    }

    public function removePluginPrivacy( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !empty($params['pluginKey']) )
        {
            PRIVACY_BOL_ActionService::getInstance()->deleteActionDataByPluginKey($params['pluginKey']);
        }
    }

    public function checkPremission( OW_Event $event )
    {

        $params = $event->getParams();

        $result = PRIVACY_BOL_ActionService::getInstance()->checkPermission($params);

        if ( $result['blocked'] )
        {
            $ownerId = (int) $params['ownerId'];

            $username = BOL_UserService::getInstance()->getUserName($ownerId);

            $exception = new RedirectException(OW::getRouter()->urlForRoute('privacy_no_permission', array('username' => $username)));

            $params['message'] = $result['message'];
            $params['privacy'] = $result['privacy'];

            OW::getSession()->set('privacyRedirectExceptionMessage', $params['message']);

            $exception->setData($params);

            throw $exception;
        }
    }

    public function checkPremissionForUserList( OW_Event $event )
    {
        $params = $event->getParams();

        $action = $params['action'];
        $ownerIdList = $params['ownerIdList'];
        $viewerId = $params['viewerId'];

        return PRIVACY_BOL_ActionService::getInstance()->checkPermissionForUserList($action, $ownerIdList, $viewerId);
    }

    public function permissionEverybody( BASE_CLASS_EventCollector $event )
    {
        $params = $event->getParams();
        if ( !empty($params['privacy']) && $params['privacy'] == 'everybody' )
        {
            if ( !empty($params['ownerId']) )
            {
                $privacy = array();
                $privacy = array(
                    'everybody' => array(
                        'blocked' => false
                    ));

                $event->add($privacy);
            }
        }

        if ( !empty($params['userPrivacyList']) && is_array($params['userPrivacyList']) )
        {
            $list = $params['userPrivacyList'];
            $resultList = array();

            foreach ( $list as $ownerId => $privacy )
            {
                if ( $privacy == 'everybody' )
                {
                    $privacy = array(
                        'privacy' => $privacy,
                        'blocked' => false,
                        'userId' => $ownerId
                    );
                    $event->add($privacy);
                }
            }
        }
    }

    public function permissionOnlyForMe( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();

        $params = $event->getParams();

        if ( !empty($params['privacy']) && $params['privacy'] == 'only_for_me' )
        {
            if ( !empty($params['ownerId']) )
            {
                $ownerId = (int) $params['ownerId'];
                $viewerId = (int) $params['viewerId'];

                $item = array();
                $item = array(
                    'only_for_me' => array(
                        'blocked' => true,
                    ));

                if ( $ownerId > 0 && $ownerId === $viewerId )
                {
                    $item = array(
                        'only_for_me' => array(
                            'blocked' => false
                        ));
                }

                $event->add($item);
            }
        }


        if ( !empty($params['userPrivacyList']) && is_array($params['userPrivacyList']) )
        {
            $list = $params['userPrivacyList'];

            $viewerId = (int) $params['viewerId'];

            $resultList = array();

            foreach ( $list as $ownerId => $privacy )
            {
                if ( $privacy == 'only_for_me' )
                {
                    $privacy = array(
                        'privacy' => $privacy,
                        'blocked' => true,
                        'userId' => $ownerId
                    );

                    if ( $ownerId > 0 && $ownerId === $viewerId )
                    {
                        $privacy = array(
                            'privacy' => $privacy,
                            'blocked' => false,
                            'userId' => $ownerId
                        );
                    }

                    $event->add($privacy);
                }
            }
        }
    }

    public function pluginIsActive()
    {
        return true;
    }

    public function genericInit()
    {
        OW::getEventManager()->bind('base.preference_menu_items', array($this, 'addPrivacyPreferenceMenuItem'));
        OW::getEventManager()->bind(PRIVACY_BOL_ActionService::EVENT_GET_PRIVACY_LIST, array($this, 'addPrivacy'));
        OW::getEventManager()->bind('plugin.privacy.get_privacy', array($this, 'getActionPrivacy'));
        OW::getEventManager()->bind('plugin.privacy.get_main_privacy', array($this, 'getActionMainPrivacyByOwnerIdList'));
        OW::getEventManager()->bind(OW_EventManager::ON_USER_UNREGISTER, array($this, 'removeUserPrivacy'));
        OW::getEventManager()->bind(OW_EventManager::ON_BEFORE_PLUGIN_UNINSTALL, array($this, 'removePluginPrivacy'));
        OW::getEventManager()->bind(PRIVACY_BOL_ActionService::EVENT_CHECK_PERMISSION, array($this, 'checkPremission'));
        OW::getEventManager()->bind(PRIVACY_BOL_ActionService::EVENT_CHECK_PERMISSION_FOR_USER_LIST, array($this, 'checkPremissionForUserList'));
        OW::getEventManager()->bind('plugin.privacy.check_permission', array($this, 'permissionEverybody'));
        OW::getEventManager()->bind('plugin.privacy.check_permission', array($this, 'permissionOnlyForMe'));
        OW::getEventManager()->bind('plugin.privacy', array($this, 'pluginIsActive'));
    }
	

    public function init()
    {
        OW::getEventManager()->bind('base.add_main_console_item', array($this, 'addConsoleItem'));
    }
}
