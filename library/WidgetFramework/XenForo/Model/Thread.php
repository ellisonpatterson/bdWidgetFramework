<?php

class WidgetFramework_XenForo_Model_Thread extends XFCP_WidgetFramework_XenForo_Model_Thread
{
    const CONDITIONS_DISCUSSION_TYPE = 'WidgetFramework_discussion_type';
    const CONDITIONS_POST_DATE = 'WidgetFramework_post_date';
    const CONDITIONS_THREAD_ID = 'WidgetFramework_thread_id';
    const CONDITIONS_THREAD_ID_NOT = 'WidgetFramework_thread_id_not';

    const FETCH_OPTIONS_FORUM_FULL_JOIN = 'WidgetFramework_forum_full_join';
    const FETCH_OPTIONS_LAST_POST_JOIN = 'WidgetFramework_last_post_join';
    const FETCH_OPTIONS_POLL_JOIN = 'WidgetFramework_poll_join';
    const FETCH_OPTIONS_ORDER_RANDOM = 'WidgetFramework_random';

    public function prepareThreadConditions(array $conditions, array &$fetchOptions)
    {
        $result = parent::prepareThreadConditions($conditions, $fetchOptions);
        $sqlConditions = array($result);

        if (!empty($conditions[self::CONDITIONS_POST_DATE])
            && is_array($conditions[self::CONDITIONS_POST_DATE])
        ) {
            list($operator, $cutOff) = $conditions[self::CONDITIONS_POST_DATE];
            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "thread.post_date $operator " . $this->_getDb()->quote($cutOff);
        }

        if (isset($conditions[self::CONDITIONS_DISCUSSION_TYPE])) {
            if (is_array($conditions[self::CONDITIONS_DISCUSSION_TYPE])) {
                $sqlConditions[] = sprintf('thread.discussion_type IN (%s)',
                    $this->_getDb()->quote($conditions[self::CONDITIONS_DISCUSSION_TYPE]));
            } else {
                $sqlConditions[] = 'thread.discussion_type = '
                    . $this->_getDb()->quote($conditions[self::CONDITIONS_DISCUSSION_TYPE]);
            }
        }

        if (isset($conditions[self::CONDITIONS_THREAD_ID])) {
            if (is_array($conditions[self::CONDITIONS_THREAD_ID])) {
                $sqlConditions[] = sprintf('thread.thread_id IN (%s)',
                    $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID]));
            } else {
                $sqlConditions[] = 'thread.thread_id = '
                    . $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID]);
            }
        }

        if (isset($conditions[self::CONDITIONS_THREAD_ID_NOT])) {
            if (is_array($conditions[self::CONDITIONS_THREAD_ID_NOT])) {
                $sqlConditions[] = sprintf('thread.thread_id NOT IN (%s)',
                    $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID_NOT]));
            } else {
                $sqlConditions[] = 'thread.thread_id <> '
                    . $this->_getDb()->quote($conditions[self::CONDITIONS_THREAD_ID_NOT]);
            }
        }

        if (count($sqlConditions) > 1) {
            // some of our conditions have been found
            return $this->getConditionsForClause($sqlConditions);
        } else {
            return $result;
        }
    }

    public function prepareThreadFetchOptions(array $fetchOptions)
    {
        $proxyFetchOptions = $fetchOptions;

        if (!empty($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN])
            && empty($fetchOptions['join'])
        ) {
            $proxyFetchOptions['join'] = $fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN];

            $lastPostJoins = array(
                self::FETCH_USER,
                self::FETCH_AVATAR,
                self::FETCH_FIRSTPOST,
            );
            foreach ($lastPostJoins as $join) {
                if ($proxyFetchOptions['join'] & $join) {
                    // remove last post joins from fetch options
                    $proxyFetchOptions['join'] ^= $join;
                }
            }
        }

        $result = parent::prepareThreadFetchOptions($proxyFetchOptions);
        $selectFields = $result['selectFields'];
        $joinTables = $result['joinTables'];
        $orderClause = $result['orderClause'];

        if (!empty($fetchOptions[self::FETCH_OPTIONS_POLL_JOIN])) {
            $selectFields .= ',
					poll.*';
            $joinTables .= '
					LEFT JOIN xf_poll AS poll ON
					(poll.content_type = \'thread\' AND content_id = thread.thread_id)';
        }

        if (!empty($fetchOptions[self::FETCH_OPTIONS_FORUM_FULL_JOIN])
            && empty($fetchOptions['join'])
        ) {
            $selectFields .= ',
					forum.*';
            $joinTables .= '
					INNER JOIN xf_forum AS forum ON
					(forum.node_id = thread.node_id)';
        }

        if (!empty($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN])
            && empty($fetchOptions['join'])
        ) {
            // IMPORTANT: update $proxyFetchOptions['join'] calculation if more fetch flags are supported

            if ($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN] & self::FETCH_USER) {
                $selectFields .= ',
						1 AS fetched_last_post_user, user.*';
                $joinTables .= '
						LEFT JOIN xf_user AS user ON
						(user.user_id = thread.last_post_user_id)';
            } elseif ($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN] & self::FETCH_AVATAR) {
                $selectFields .= ',
						1 AS fetched_last_post_user, user.gender, user.avatar_date, user.gravatar';
                $joinTables .= '
						LEFT JOIN xf_user AS user ON
						(user.user_id = thread.last_post_user_id)';
            }

            if ($fetchOptions[self::FETCH_OPTIONS_LAST_POST_JOIN] & self::FETCH_FIRSTPOST) {
                $selectFields .= ',
					1 AS fetched_last_post, post.message, post.attach_count';
                $joinTables .= '
					LEFT JOIN xf_post AS post ON
						(post.post_id = thread.last_post_id)';
            }

            // IMPORTANT: update $proxyFetchOptions['join'] calculation if more fetch flags are supported
        }

        if (!empty($fetchOptions['order'])) {
            switch ($fetchOptions['order']) {
                case self::FETCH_OPTIONS_ORDER_RANDOM:
                    $orderClause = 'ORDER BY RAND()';
                    break;
            }
        }

        return compact('selectFields', 'joinTables', 'orderClause');
    }

    public function WidgetFramework_prepareThreadForRendererThreads(
        array $thread,
        array $forum,
        array $nodePermissions = null,
        array $viewingUser = null
    ) {
        $thread = $this->prepareThread($thread, $forum, $nodePermissions, $viewingUser);

        $thread['canInlineMod'] = false;
        $thread['canEditThread'] = false;

        if (!empty($thread['fetched_last_post_user'])) {
            $thread['user_id'] = $thread['last_post_user_id'];
            $thread['username'] = $thread['last_post_username'];

            $thread['lastPostInfo'] = array_merge($thread['lastPostInfo'], array(
                'user_id' => $thread['last_post_user_id'],
                'gravatar' => $thread['gravatar'],
                'avatar_date' => $thread['avatar_date'],
            ));
        }

        if (!empty($thread['fetched_last_post'])) {
            $thread['post_id'] = $thread['last_post_id'];
        } else {
            $thread['post_id'] = $thread['first_post_id'];
        }

        $thread['forum'] = $forum;

        return $thread;
    }

}
