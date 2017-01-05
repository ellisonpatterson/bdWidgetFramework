<?php

class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer
{
    const TEMPLATE_PARAM_IGNORED_THREAD_IDS = '_WidgetFramework_WidgetFramework_Threads_ignoredThreadIds';
    const AJAX_PARAM_IGNORED_THREAD_IDS = '_ignoredThreadIds';
    const AJAX_PARAM_CURRENT_PAGE = '_currentPage';

    public function useCache(array $widget)
    {
        if (!empty($widget['options']['is_new'])) {
            return false;
        }

        return parent::useCache($widget);
    }

    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            if (!empty($widget['options']['tags'])) {
                $widget['options']['type'] = '_tags';
            }

            if (empty($widget['options']['type'])) {
                $widget['options']['type'] = 'new';
            }

            switch ($widget['options']['type']) {
                case 'recent':
                case 'recent_first_poster':
                    if (XenForo_Application::$versionId > 1050000) {
                        return new XenForo_Phrase('new_posts');
                    }

                    return new XenForo_Phrase('wf_widget_threads_type_recent');
                case 'latest_replies':
                    return new XenForo_Phrase('wf_widget_threads_type_latest_replies');
                case 'popular':
                    return new XenForo_Phrase('wf_widget_threads_type_popular');
                case 'most_replied':
                    return new XenForo_Phrase('wf_widget_threads_type_most_replied');
                case 'most_liked':
                    return new XenForo_Phrase('wf_widget_threads_type_most_liked');
                case 'polls':
                    return new XenForo_Phrase('wf_widget_threads_type_polls');
                case '_tags':
                    return new XenForo_Phrase('wf_widget_threads_type__tags');
                case 'new':
                default:
                    return new XenForo_Phrase('wf_widget_threads_type_new');
            }
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => 'Threads',
            'options' => array(
                'type' => XenForo_Input::STRING,
                'cutoff' => XenForo_Input::UINT,
                'forums' => XenForo_Input::ARRAY_SIMPLE,
                'sticky' => XenForo_Input::STRING,
                'prefixes' => XenForo_Input::ARRAY_SIMPLE,
                'tags' => XenForo_Input::ARRAY_SIMPLE,
                'tags_all' => XenForo_Input::UINT,
                'open_only' => XenForo_Input::UINT,
                'as_guest' => XenForo_Input::UINT,
                'is_new' => XenForo_Input::UINT,
                'order_reverted' => XenForo_Input::UINT,
                'limit' => XenForo_Input::UINT,
                'layout' => XenForo_Input::STRING,
                'layout_options' => XenForo_Input::ARRAY_SIMPLE,
            ),
            'useCache' => true,
            'useUserCache' => true,
            'cacheSeconds' => 300, // cache for 5 minutes
            'canAjaxLoad' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'wf_widget_options_threads';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $params = $template->getParams();

        $forums = empty($params['options']['forums']) ? array() : $params['options']['forums'];
        $forums = $this->_helperPrepareForumsOptionSource($forums, true);
        $template->setParam('forums', $forums);

        /** @var XenForo_Model_ThreadPrefix $threadPrefixModel */
        $threadPrefixModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_ThreadPrefix');
        $prefixes = $threadPrefixModel->getPrefixOptions();
        foreach ($prefixes as $prefixGroupId => &$groupPrefixes) {
            foreach ($groupPrefixes as &$prefix) {
                if (!empty($params['options']['prefixes'])
                    && in_array($prefix['value'], $params['options']['prefixes'])
                ) {
                    $prefix['selected'] = true;
                }
            }
        }
        $template->setParam('prefixes', $prefixes);

        $contentTaggingFound = WidgetFramework_Core::contentTaggingFound();
        if ($contentTaggingFound) {
            $template->setParam('contentTaggingFound', $contentTaggingFound);

            $tags = array();
            if (!empty($params['options']['tags'])) {
                foreach ($params['options']['tags'] as $tag) {
                    if (!empty($tag['tag_id'])) {
                        $tags[] = $tag['tag'];
                    }
                }
            }
            $template->setParam('tags', implode(', ', $tags));
        }

        /** @var XenForo_Model_Style $styleModel */
        $styleModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Style');
        /** @var XenForo_Model_StyleProperty $stylePropertyModel */
        $stylePropertyModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_StyleProperty');
        $defaultStyleId = XenForo_Application::getOptions()->get('defaultStyleId');
        $template->setParam('defaultStyle', $styleModel->getStyleById($defaultStyleId));
        $styleProperties = $stylePropertyModel->getEffectiveStylePropertiesInStyle($defaultStyleId);
        $propertyNamePrefix = 'wf_threads_';
        $defaultStyleProperties = array();
        foreach ($styleProperties as $styleProperty) {
            if ($styleProperty['group_name'] !== 'WidgetFramework_Threads') {
                continue;
            }

            $propertyName = $styleProperty['property_name'];
            if (strpos($propertyName, $propertyNamePrefix) !== 0) {
                continue;
            }
            $propertyName = substr($propertyName, strlen($propertyNamePrefix));

            $defaultStyleProperties[$propertyName] = $styleProperty['property_value'];
        }
        $template->setParam('defaultStyleProperties', $defaultStyleProperties);

        return parent::_renderOptions($template);
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        switch ($optionKey) {
            case 'tags':
                if (WidgetFramework_Core::contentTaggingFound()
                    && !empty($optionValue[0])
                ) {
                    /** @var XenForo_Model_Tag $tagModel */
                    $tagModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Tag');
                    $tags = $tagModel->splitTags($optionValue[0]);
                    $optionValue = $tagModel->getTags($tags);
                } else {
                    $optionValue = null;
                }
                break;
            case 'layout_options':
                if (is_array($optionValue)
                    && count($optionValue) === 1
                ) {
                    $firstSubOptionValue = reset($optionValue);
                    if (is_array($firstSubOptionValue)) {
                        $optionValue = $firstSubOptionValue;
                    }
                }
                break;
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        if (!empty($widget['options']['layout'])
            && $widget['options']['layout'] === 'custom'
            && !empty($widget['options']['layout_options']['customTemplateTitle'])
        ) {
            return $widget['options']['layout_options']['customTemplateTitle'];
        }

        return 'wf_widget_threads';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        if (empty($widget['options']['limit'])) {
            $widget['options']['limit'] = 5;
        }
        if (empty($widget['options']['cutoff'])) {
            $widget['options']['cutoff'] = 5;
        }
        if (empty($widget['options']['type'])) {
            $widget['options']['type'] = 'new';
        }

        $ignoredThreadIds = array();
        if (isset($widget['_ajaxLoadParams'][self::AJAX_PARAM_IGNORED_THREAD_IDS])) {
            $ignoredThreadIds = array_map('intval', preg_split('#[^0-9]#',
                $widget['_ajaxLoadParams'][self::AJAX_PARAM_IGNORED_THREAD_IDS], -1, PREG_SPLIT_NO_EMPTY));
        }
        $params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS] = $ignoredThreadIds;

        if (empty($widget['options']['layout'])) {
            if (!empty($params[WidgetFramework_Core::PARAM_IS_HOOK])) {
                $layout = 'list';
            } else {
                $layout = 'sidebar';
            }
        } else {
            $layout = $widget['options']['layout'];
        }
        $layoutOptions = $this->_getLayoutOptions($widget, $positionCode, $params, $layout);
        $renderTemplateObject->setParam('layout', $layoutOptions['layout']);
        $renderTemplateObject->setParam('layoutOptions', $layoutOptions);

        $threads = $this->_getThreads($widget, $positionCode, $params, $renderTemplateObject);
        $renderTemplateObject->setParam('threads', $threads);

        if ($layout === 'list_compact'
            && $layoutOptions['listCompactMore'] > 0
            && count($threads) >= $widget['options']['limit']
            && $this->_supportIgnoredThreadIds()
        ) {
            foreach ($threads as $thread) {
                $params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS][] = $thread['thread_id'];
            }

            $currentPage = 0;
            if (isset($widget['_ajaxLoadParams'][self::AJAX_PARAM_CURRENT_PAGE])) {
                $currentPage = $widget['_ajaxLoadParams'][self::AJAX_PARAM_CURRENT_PAGE];
            }

            if ($currentPage < $layoutOptions['listCompactMore']) {
                $loadMoreUrl = $this->getAjaxLoadUrl($widget, $positionCode, $params, $renderTemplateObject);
                $renderTemplateObject->setParam('loadMoreUrl', $loadMoreUrl);
            }
        }

        return $renderTemplateObject->render();
    }

    protected function _getExtraDataLink(array $widget)
    {
        if (XenForo_Application::$versionId > 1050000
            && $widget['options']['type'] === 'recent'
        ) {
            return XenForo_Link::buildPublicLink('find-new/posts');
        }

        return parent::_getExtraDataLink($widget);
    }


    public function useUserCache(array $widget)
    {
        if (!empty($widget['options']['as_guest'])) {
            // using guest permission
            // there is no reason to use the user cache
            return false;
        }

        return parent::useUserCache($widget);
    }

    public function useWrapper(array $widget)
    {
        if (!empty($widget['options']['layout'])
            && $widget['options']['layout'] === 'full'
        ) {
            // using full layout, do not use wrapper
            return false;
        }

        if (!empty($widget['options']['layout'])
            && $widget['options']['layout'] === 'custom'
        ) {
            return !empty($widget['options']['layout_options']['use_wrapper']);
        }

        return parent::useWrapper($widget);
    }

    protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
    {
        if (!empty($widget['options']['forums'])
            && $this->_helperDetectSpecialForums($widget['options']['forums'])
        ) {
            $forumId = $this->_helperGetForumIdForCache($widget['options']['forums'], $params,
                !empty($widget['options']['as_guest']));
            if (!empty($forumId)) {
                $suffix[] = 'f' . $forumId;
            }
        }

        return parent::_getCacheId($widget, $positionCode, $params, $suffix);
    }

    /**
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param string $layout
     * @return array
     */
    protected function _getLayoutOptions($widget, $positionCode, $params, $layout)
    {
        $layoutOptions = array(
            'layout' => $layout,
            'getPosts' => false,
        );

        $rawLayoutOptions = array();
        if (isset($widget['options']['layout_options'])) {
            $rawLayoutOptions = $widget['options']['layout_options'];
        }

        $stylePropertyIds = array();
        switch ($layout) {
            case 'sidebar':
                $stylePropertyIds[] = 'rich';
                $stylePropertyIds[] = 'titleMaxLength';
                $stylePropertyIds[] = 'showPrefix';
                break;
            case 'sidebar_snippet':
                $stylePropertyIds[] = 'titleMaxLength';
                $stylePropertyIds[] = 'snippetMaxLength';
                $stylePropertyIds[] = 'showPrefix';
                $layoutOptions['layout'] = 'sidebar';
                $layoutOptions['getPosts'] = true;
                break;
            case 'list':
                // TODO
                break;
            case 'list_compact':
                $stylePropertyIds[] = 'rich';
                $stylePropertyIds[] = 'listCompactTitleMaxLength';
                $stylePropertyIds[] = 'listCompactShowPrefix';
                $stylePropertyIds[] = 'listCompactAvatar';
                $stylePropertyIds[] = 'listCompactUser';
                $stylePropertyIds[] = 'listCompactForum';
                $stylePropertyIds[] = 'listCompactDate';
                $stylePropertyIds[] = 'listCompactViewCount';
                $stylePropertyIds[] = 'listCompactFirstPostLikes';
                $stylePropertyIds[] = 'listCompactReplyCount';
                $stylePropertyIds[] = 'listCompactMore';
                break;
            case 'full':
                $stylePropertyIds[] = 'rich';
                $stylePropertyIds[] = 'fullMaxLength';
                $stylePropertyIds[] = 'fullInfoBottom';
                $stylePropertyIds[] = 'fullUser';
                $stylePropertyIds[] = 'fullForum';
                $stylePropertyIds[] = 'fullDate';
                $stylePropertyIds[] = 'fullViewCount';
                $stylePropertyIds[] = 'fullFirstPostLikes';
                $stylePropertyIds[] = 'fullReplyCount';
                $layoutOptions['getPosts'] = true;
                break;
            case 'custom':
                $layoutOptions += $rawLayoutOptions;
                $layoutOptions['getPosts'] = !empty($rawLayoutOptions['getPosts']);
                break;
        }

        foreach ($stylePropertyIds as $stylePropertyId) {
            $layoutOptions[$stylePropertyId] = XenForo_Template_Helper_Core::styleProperty('wf_threads_' . $stylePropertyId);
            if (isset($rawLayoutOptions[$stylePropertyId])
                && is_string($rawLayoutOptions[$stylePropertyId])
                && $rawLayoutOptions[$stylePropertyId] !== ''
            ) {
                $layoutOptions[$stylePropertyId] = $rawLayoutOptions[$stylePropertyId];
            }
        }

        return $layoutOptions;
    }

    /**
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     * @return array $threads
     */
    protected function _getThreads($widget, $positionCode, $params, $renderTemplateObject)
    {
        if ($positionCode === 'forum_list'
            && isset($params['threads'])
            && empty($layoutOptions['getPosts'])
            && $widget['options']['type'] === 'recent'
            && $widget['options']['limit'] == XenForo_Application::getOptions()->get('forumListNewPosts')
        ) {
            return $this->_prepareForumListNewPosts($params['threads']);
        }

        if (!empty($widget['_ajaxLoadParams']['forumIds'])) {
            $forumIds = $widget['_ajaxLoadParams']['forumIds'];
        } else {
            $forumIds = $this->_helperGetForumIdsFromOption(empty($widget['options']['forums'])
                ? array() : $widget['options']['forums'], $params,
                empty($widget['options']['as_guest']) ? false : true);
        }
        if (empty($forumIds)) {
            // no forum ids?! Save the effort and return asap
            // btw, because XenForo_Model_Thread::prepareThreadConditions ignores empty
            // node_id in $conditions, continuing may result in incorrect output (could be a
            // serious bug)
            return array();
        }

        $conditions = array(
            'node_id' => $forumIds,
            'deleted' => false,
            'moderated' => false,
        );

        if (!empty($params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS])) {
            WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Thread');
            $conditions[WidgetFramework_Model_Thread::CONDITIONS_THREAD_ID_NOT]
                = $params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS];
        }

        // process sticky
        // since 2.4.7
        if (isset($widget['options']['sticky'])
            && is_numeric($widget['options']['sticky'])
        ) {
            $conditions['sticky'] = intval($widget['options']['sticky']);
        }

        // process prefix
        // since 1.3.4
        if (!empty($widget['options']['prefixes'])) {
            $conditions['prefix_id'] = $widget['options']['prefixes'];
        }

        // process discussion_open
        // since 2.5
        if (!empty($widget['options']['open_only'])) {
            $conditions['discussion_open'] = true;
        }

        // since 2.6.3
        if (WidgetFramework_Core::contentTaggingFound()
            && !empty($widget['options']['tags'])
        ) {
            $threadIds = array();

            /* @var $searchModel XenForo_Model_Search */
            $searchModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Search');
            $constraintsKey = !empty($widget['options']['tags_all']) ? 'tag' : 'tag_any';
            $constraints = array($constraintsKey => implode(' ', array_keys($widget['options']['tags'])));

            $searcher = new XenForo_Search_Searcher($searchModel);
            $searchQuery = '';
            $order = 'date';

            $typeHandler = $searchModel->getSearchDataHandler('thread');
            $results = $searcher->searchType($typeHandler, $searchQuery, $constraints,
                $order, false, $widget['options']['limit'] * 10);
            foreach ($results as $result) {
                if ($result[0] === 'thread') {
                    $threadIds[] = $result[1];
                }
            }
            if (empty($threadIds)) {
                return array();
            }

            WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Thread');
            $conditions[WidgetFramework_Model_Thread::CONDITIONS_THREAD_ID] = $threadIds;
        }

        return $this->_getThreadsWithConditions($conditions, $widget, $positionCode, $params, $renderTemplateObject);
    }

    /**
     * @param array $conditions
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     * @return array $threads
     */
    protected function _getThreadsWithConditions(
        array $conditions,
        $widget,
        $positionCode,
        $params,
        $renderTemplateObject
    ) {
        // note: `limit` is set to 3 times of configured limit to account for the threads
        // that get hidden because of deep permissions like viewOthers or viewContent
        $fetchOptions = array(
            'limit' => $widget['options']['limit'] * 3,
            'order' => 'post_date',
            'direction' => empty($widget['options']['order_reverted']) ? 'desc' : 'asc',
        );

        $fetchLastPost = false;
        $readUserId = 0;

        // include is_new if option is turned on
        // since 2.5.1
        if (!empty($widget['options']['is_new'])) {
            $readUserId = XenForo_Visitor::getUserId();
        }

        switch ($widget['options']['type']) {
            case 'recent':
                $fetchOptions['order'] = 'last_post_date';
                $fetchLastPost = true;
                break;
            case 'recent_first_poster':
                $fetchOptions['order'] = 'last_post_date';
                break;
            case 'latest_replies':
                $conditions['reply_count'] = array('>', 0);
                $fetchOptions['order'] = 'last_post_date';
                $fetchLastPost = true;
                break;
            case 'popular':
                $conditions['post_date'] = array(
                    '>',
                    XenForo_Application::$time - $widget['options']['cutoff'] * 86400
                );
                $fetchOptions['order'] = 'view_count';
                break;
            case 'most_replied':
                $conditions['reply_count'] = array('>', 0);
                $conditions['post_date'] = array(
                    '>',
                    XenForo_Application::$time - $widget['options']['cutoff'] * 86400
                );
                $fetchOptions['order'] = 'reply_count';
                break;
            case 'most_liked':
                $conditions['first_post_likes'] = array('>', 0);
                $conditions['post_date'] = array(
                    '>',
                    XenForo_Application::$time - $widget['options']['cutoff'] * 86400
                );
                $fetchOptions['order'] = 'first_post_likes';
                break;
            case 'polls':
                $conditions['discussion_type'] = 'poll';
                $fetchOptions['order'] = 'post_date';
                break;
        }

        /** @var WidgetFramework_Model_Thread $wfThreadModel */
        $wfThreadModel = WidgetFramework_Core::getInstance()->getModelFromCache('WidgetFramework_Model_Thread');
        $threadIds = $wfThreadModel->getThreadIds($conditions, $fetchOptions);
        if (empty($threadIds)) {
            return array();
        }

        $threads = $wfThreadModel->getThreadsByIdsInOrder($threadIds, 0, $readUserId);
        if (empty($threads)) {
            return array();
        }

        if ($fetchLastPost) {
            foreach ($threads as &$threadRef) {
                $threadRef['wf_requested_last_post'] = true;
            }
        }
        $this->_prepareThreads($widget, $positionCode, $params, $renderTemplateObject, $threads);

        if (count($threads) > $widget['options']['limit']) {
            // too many threads (because we fetched 3 times as needed)
            $threads = array_slice($threads, 0, $widget['options']['limit'], true);
        }

        return $threads;
    }

    /**
     * @param array $widget
     * @param string $positionCode
     * @param array $params
     * @param XenForo_Template_Abstract $renderTemplateObject
     * @param array $threads
     */
    protected function _prepareThreads(
        /** @noinspection PhpUnusedParameterInspection */
        array $widget,
        $positionCode,
        array $params,
        $renderTemplateObject,
        array &$threads
    ) {
        if (empty($threads)) {
            return;
        }

        $core = WidgetFramework_Core::getInstance();
        $layoutOptions = $renderTemplateObject->getParam('layoutOptions');
        $getPosts = !empty($layoutOptions['getPosts']);

        /** @var XenForo_Model_Thread $threadModel */
        $threadModel = $core->getModelFromCache('XenForo_Model_Thread');
        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = $core->getModelFromCache('XenForo_Model_Node');
        /** @var XenForo_Model_Forum $forumModel */
        $forumModel = $core->getModelFromCache('XenForo_Model_Forum');
        /** @var XenForo_Model_User $userModel */
        $userModel = $core->getModelFromCache('XenForo_Model_User');
        /** @var XenForo_Model_Post $postModel */
        $postModel = $core->getModelFromCache('XenForo_Model_Post');
        /** @var WidgetFramework_Model_Thread $wfThreadModel */
        $wfThreadModel = $core->getModelFromCache('WidgetFramework_Model_Thread');

        $permissionCombinationId = empty($widget['options']['as_guest']) ? null : 1;
        $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination($permissionCombinationId);
        $viewingUser = (empty($widget['options']['as_guest']) ? null : $userModel->getVisitingGuestUser());
        $viewingUserId = $viewingUser === null ? XenForo_Visitor::getUserId() : $viewingUser['user_id'];

        $forumIds = array();
        $userIds = array();
        $postIds = array();
        foreach ($threads as &$threadRef) {
            $forumIds[] = intval($threadRef['node_id']);
            if (!empty($threadRef['wf_requested_last_post'])) {
                $threadRef['wf_requested_user_id'] = intval($threadRef['last_post_user_id']);
                $threadRef['wf_requested_post_id'] = intval($threadRef['last_post_id']);
            } else {
                $threadRef['wf_requested_user_id'] = intval($threadRef['user_id']);
                $threadRef['wf_requested_post_id'] = intval($threadRef['first_post_id']);
            }
            $userIds[] = $threadRef['wf_requested_user_id'];
            $postIds[] = $threadRef['wf_requested_post_id'];
        }
        $forums = $forumModel->getForumsByIds(array_unique($forumIds));
        $users = $userModel->getUsersByIds(array_unique($userIds));

        $posts = array();
        $viewObj = self::getViewObject($params, $renderTemplateObject);
        if ($getPosts && !empty($viewObj)) {
            $posts = $postModel->getPostsByIds($postIds, array('likeUserId' => $viewingUserId));
            $posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);

            $bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $viewObj));
            $bbCodeParser = XenForo_BbCode_Parser::create($bbCodeFormatter);
            $bbCodeOptions = array(
                'states' => array(),
                'contentType' => 'post',
                'contentIdKey' => 'post_id'
            );
        }

        foreach (array_keys($threads) as $threadId) {
            $threadRef = &$threads[$threadId];

            if (empty($nodePermissions[$threadRef['node_id']])) {
                unset($threads[$threadId]);
                continue;
            }
            $permissionsRef = &$nodePermissions[$threadRef['node_id']];

            if (empty($forums[$threadRef['node_id']])) {
                unset($threads[$threadId]);
                continue;
            }
            $forumRef = &$forums[$threadRef['node_id']];
            $threadRef['forum'] = $forumRef;

            if ($threadModel->isRedirect($threadRef)) {
                unset($threads[$threadId]);
                continue;
            }

            if (!$threadModel->canViewThreadAndContainer($threadRef, $forumRef, $null, $permissionsRef, $viewingUser)) {
                unset($threads[$threadId]);
                continue;
            }

            if (isset($users[$threadRef['wf_requested_user_id']])) {
                $userRef =& $users[$threadRef['wf_requested_user_id']];
                $threadRef = array_merge($threadRef, $userRef);
            }

            if (isset($posts[$threadRef['wf_requested_post_id']])) {
                // mimics XenForo_Model_Thread::FETCH_FIRSTPOST behavior
                // for full post data, keep it within $threadRef.post
                $postRef =& $posts[$threadRef['wf_requested_post_id']];
                $threadRef['post_id'] = $postRef['post_id'];
                $threadRef['attach_count'] = $postRef['attach_count'];
                $threadRef['message'] = $postRef['message'];
                if (isset($postRef['attachments'])) {
                    $threadRef['attachments'] = $postRef['attachments'];
                }
                $threadRef['post'] = $postRef;
            }
            if (!empty($bbCodeParser)
                && !empty($bbCodeOptions)
                && isset($threadRef['post'])
            ) {
                $threadBbCodeOptions = $bbCodeOptions;
                $threadBbCodeOptions['states']['viewAttachments'] =
                    $threadModel->canViewAttachmentsInThread($threadRef, $forumRef, $null,
                        $permissionsRef, $viewingUser);
                $threadRef['messageHtml'] = WidgetFramework_ShippableHelper_Html::preSnippet(
                    $threadRef, $bbCodeParser, $threadBbCodeOptions);
                $threadRef['post'] = $postModel->preparePost($threadRef['post'],
                    $threadRef, $forumRef, $permissionsRef, $viewingUser);
            }

            $threadRef = $wfThreadModel->prepareThreadForRendererThreads($threadRef,
                $forumRef, $permissionsRef, $viewingUser);
        }
    }

    protected function _prepareForumListNewPosts($threads)
    {
        foreach ($threads as &$threadRef) {
            if (!isset($threadRef['lastPostInfo'])) {
                continue;
            }

            $threadRef += $threadRef['lastPostInfo'];
        }

        return $threads;
    }

    protected function _getAjaxLoadParams(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $template
    ) {
        $ajaxLoadParams = parent::_getAjaxLoadParams($widget, $positionCode, $params, $template);

        if (isset($widget['options']['forums']) && $this->_helperDetectSpecialForums($widget['options']['forums'])) {
            $forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params,
                empty($widget['options']['as_guest']) ? false : true);
            $ajaxLoadParams['forumIds'] = $forumIds;
        }

        if (isset($params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS])
            && is_array($params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS])
        ) {
            $ajaxLoadParams[self::AJAX_PARAM_IGNORED_THREAD_IDS] = implode(',',
                array_unique(array_map('intval', $params[self::TEMPLATE_PARAM_IGNORED_THREAD_IDS])));
        }

        if (isset($widget['_ajaxLoadParams'][self::AJAX_PARAM_CURRENT_PAGE])) {
            $ajaxLoadParams[self::AJAX_PARAM_CURRENT_PAGE] = $widget['_ajaxLoadParams'][self::AJAX_PARAM_CURRENT_PAGE] + 1;
        } else {
            $ajaxLoadParams[self::AJAX_PARAM_CURRENT_PAGE] = 1;
        }

        return $ajaxLoadParams;
    }

    protected function _supportIgnoredThreadIds()
    {
        return get_class($this) === 'WidgetFramework_WidgetRenderer_Threads';
    }
}
