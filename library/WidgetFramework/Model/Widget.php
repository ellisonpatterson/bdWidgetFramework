<?php

class WidgetFramework_Model_Widget extends XenForo_Model
{
    const SIMPLE_CACHE_KEY = 'widgets';

    public function createGroupContaining(array $widget)
    {
        $groupDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        $groupDw->bulkSet(array(
            'class' => 'WidgetFramework_WidgetGroup',
            'position' => $widget['position'],
            'display_order' => $widget['display_order'],
            'active' => 1,
        ));
        $groupDw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);
        $groupDw->save();

        return $groupDw->getMergedData();
    }

    public function getWidgetTitlePhrase($widgetId)
    {
        if ($widgetId > 0) {
            return '_widget_title_' . $widgetId;
        } else {
            throw new XenForo_Exception('Cannot get widget title phrase for widget without ID.');
        }
    }

    public function getWidgetsContainsWidgetId(array $widgets, $widgetId, $groupId = 0)
    {
        foreach (array_keys($widgets) as $_widgetId) {
            if ($_widgetId === $widgetId) {
                return $widgets;
            }

            if (isset($widgets[$_widgetId]['widgets'])) {
                $response = $this->getWidgetsContainsWidgetId($widgets[$_widgetId]['widgets'], $widgetId, $groupId);

                if (!empty($response)) {
                    if (!empty($groupId)
                        && $_widgetId == $groupId
                    ) {
                        return $widgets[$_widgetId]['widgets'];
                    }

                    return $response;
                }
            }
        }

        return array();
    }

    public function countRealWidgetInWidgets(array $widgets)
    {
        $count = 0;

        foreach (array_keys($widgets) as $_widgetId) {
            if (isset($widgets[$_widgetId]['widgets'])) {
                $count += $this->countRealWidgetInWidgets($widgets[$_widgetId]['widgets']);
            } else {
                $count++;
            }
        }

        return $count;
    }

    public function getLastDisplayOrder($widgets, $groupId = 0)
    {
        if ($groupId > 0) {
            // put into a group
            $siblingWidgets = $this->getWidgetsContainsWidgetId($widgets, $groupId);
            if (!empty($siblingWidgets[$groupId]['widgets'])) {
                $siblingWidgets = $siblingWidgets[$groupId]['widgets'];
            }
        } else {
            // put into a position
            $siblingWidgets = $widgets;
        }

        $maxDisplayOrder = false;
        foreach ($siblingWidgets as $siblingWidget) {
            if ($maxDisplayOrder === false
                || $maxDisplayOrder < $siblingWidget['display_order']
            ) {
                $maxDisplayOrder = $siblingWidget['display_order'];
            }
        }

        return floor($maxDisplayOrder / 10) * 10 + 10;
    }

    public function getDisplayOrderFromRelative($widgetId, $groupId, $relativeDisplayOrder, $positionWidgetGroups, $positionWidget = null, array &$widgetsNeedUpdate = array())
    {
        if (!empty($positionWidget)) {
            // put into a group
            $sameDisplayOrderLevels = $this->getWidgetsContainsWidgetId($positionWidgetGroups, $positionWidget['widget_id'], $groupId);
        } else {
            // put into a position
            $sameDisplayOrderLevels = $positionWidgetGroups;
        }

        // sort asc by display order (ignore negative/positive)
        uasort($sameDisplayOrderLevels, array(
            'WidgetFramework_Helper_Sort',
            'widgetsByDisplayOrderAsc'
        ));
        $isNegative = $relativeDisplayOrder < 0;
        foreach (array_keys($sameDisplayOrderLevels) as $sameDisplayOrderLevelWidgetId) {
            if (($sameDisplayOrderLevels[$sameDisplayOrderLevelWidgetId]['display_order'] < 0) == $isNegative) {
                // same negative/positive
            } else {
                unset($sameDisplayOrderLevels[$sameDisplayOrderLevelWidgetId]);
            }
        }

        $reorderedWidgets = array();
        $thisWidget = false;
        $smallestDisplayOrder = false;
        if (isset($sameDisplayOrderLevels[$widgetId])) {
            $smallestDisplayOrder = $sameDisplayOrderLevels[$widgetId]['display_order'];
            $thisWidget = $sameDisplayOrderLevels[$widgetId];

            // ignore current widget before calculating display order
            unset($sameDisplayOrderLevels[$widgetId]);
        }

        $iStart = -1;
        foreach ($sameDisplayOrderLevels as $sameDisplayOrderLevelWidgetId => $sameDisplayOrderLevel) {
            if ($sameDisplayOrderLevel['display_order'] < 0) {
                // calculate correct starting relative order for negative orders
                $iStart--;
            }
        }

        $i = $iStart;
        foreach ($sameDisplayOrderLevels as $sameDisplayOrderLevelWidgetId => $sameDisplayOrderLevel) {
            $i++;

            if ($i == $relativeDisplayOrder) {
                // insert our widget
                $reorderedWidgets[$widgetId] = $thisWidget;
            }

            $reorderedWidgets[$sameDisplayOrderLevelWidgetId] = $sameDisplayOrderLevel;

            if ($smallestDisplayOrder === false OR $smallestDisplayOrder > $sameDisplayOrderLevel['display_order']) {
                $smallestDisplayOrder = $sameDisplayOrderLevel['display_order'];
            }
        }
        if (!isset($reorderedWidgets[$widgetId])) {
            // our widget is the last in the reordered list
            $reorderedWidgets[$widgetId] = $thisWidget;
        }

        $currentDisplayOrder = $smallestDisplayOrder;
        if ($isNegative) {
            // for negative orders, we have to make sure display order does not reach 0
            $currentDisplayOrder = min($currentDisplayOrder, $this->countRealWidgetInWidgets($reorderedWidgets) * -10);
        }

        $foundDisplayOrder = PHP_INT_MAX;
        foreach ($reorderedWidgets as $reorderedWidgetId => $reorderedWidget) {
            if ($currentDisplayOrder != $reorderedWidget['display_order']) {
                $widgetsNeedUpdate[$reorderedWidgetId]['display_order'] = $currentDisplayOrder;
            }

            if ($reorderedWidgetId == $widgetId) {
                $foundDisplayOrder = $currentDisplayOrder;
            }

            $currentDisplayOrder += 10;
        }

        return $foundDisplayOrder;
    }

    public function updatePositionGroupAndDisplayOrderForWidgets(
        $widgetId,
        $newPosition,
        $newGroupId,
        $newDisplayOrder,
        array $widgetsAtOldPosition,
        array &$widgetsNeedUpdate)
    {
        $siblingWidgets = $this->getWidgetsContainsWidgetId($widgetsAtOldPosition, $widgetId);
        if (empty($siblingWidgets)) {
            return false;
        }
        if (isset($siblingWidgets[$widgetId])) {
            unset($siblingWidgets[$widgetId]);
        }

        $i = -1;
        $currentDisplayOrder = $newDisplayOrder;
        foreach ($siblingWidgets as $siblingWidgetId => $siblingWidget) {
            $i++;

            if ($siblingWidget['position'] != $newPosition) {
                $widgetsNeedUpdate[$siblingWidgetId]['position'] = $newPosition;
            }

            if ($siblingWidget['display_order'] <= $currentDisplayOrder) {
                $currentDisplayOrder = floor($currentDisplayOrder / 10) * 10 + 10;
                $widgetsNeedUpdate[$siblingWidgetId]['display_order'] = $currentDisplayOrder;
            } else {
                $currentDisplayOrder = $siblingWidget['display_order'];
            }

            if ($siblingWidget['group_id'] != $newGroupId) {
                if (!empty($siblingWidget['widgets'])) {
                    foreach (array_keys($siblingWidget['widgets']) as $subWidgetId) {
                        // update all widgets within the updated group
                        $this->updatePositionGroupAndDisplayOrderForWidgets(
                            $subWidgetId,
                            $newPosition,
                            $siblingWidget['widgets'][$subWidgetId]['group_id'],
                            $siblingWidget['widgets'][$subWidgetId]['display_order'],
                            $siblingWidget['widgets'],
                            $widgetsNeedUpdate
                        );
                    }
                }

                $widgetsNeedUpdate[$siblingWidgetId]['group_id'] = $newGroupId;
            }
        }

        return true;
    }

    public function importFromFile($fileName, $deleteAll = false)
    {
        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_file_name_requested_file_not_read'), true);
        }

        try {
            $document = new SimpleXMLElement($fileName, 0, true);
        } catch (Exception $e) {
            throw new XenForo_Exception(new XenForo_Phrase('provided_file_was_not_valid_xml_file'), true);
        }

        if ($document->getName() != 'widget_framework'
            || empty($document->widget)
        ) {
            throw new XenForo_Exception(new XenForo_Phrase('wf_provided_file_is_not_an_widgets_xml_file'), true);
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $widgets = XenForo_Helper_DevelopmentXml::fixPhpBug50670($document->widget);

        XenForo_Db::beginTransaction();

        if ($deleteAll) {
            // get global widgets from database and delete them all!
            // NOTE: ignore widget page widgets
            $existingWidgets = $this->getGlobalWidgets(false, false);
            foreach ($existingWidgets as $existingWidget) {
                $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
                $dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);

                $dw->setExistingData($existingWidget);

                $dw->delete();
            }
        }

        foreach ($widgets as $widget) {
            $dw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
            $dw->setExtraData(WidgetFramework_DataWriter_Widget::EXTRA_DATA_SKIP_REBUILD, true);

            $dw->bulkSet($widget, array('ignoreInvalidFields' => true));
            $dw->set('options', unserialize(XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($widget->options)));

            $dw->save();
        }

        $this->buildCache();

        XenForo_Db::commit();
    }

    public function getGlobalWidgets($useCached = true, $prepare = true)
    {
        $widgets = false;

        /* try to use cached data */
        if ($useCached) {
            $widgets = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_KEY);
        }

        /* fallback to database */
        if ($widgets === false) {
            $widgets = $this->getwidgets(array(
                'widget_page_id' => 0,
            ));
        }

        foreach ($widgets as &$widget) {
            if ($prepare) {
                $this->prepareWidget($widget);
            }
        }

        return $widgets;
    }

    public function getPageWidgets($widgetPageId, $prepare = true)
    {
        $widgets = $this->getWidgets(array(
            'widget_page_id' => $widgetPageId,
        ));

        foreach ($widgets as &$widget) {
            if ($prepare) {
                $this->prepareWidget($widget);
            }
        }

        return $widgets;
    }

    public function getWidgetById($widgetId, array $fetchOptions = array())
    {
        $widgets = $this->getWidgets(array(
            'widget_id' => $widgetId,
        ), $fetchOptions);

        return reset($widgets);
    }

    public function getWidgets(array $conditions = array(), array $fetchOptions = array())
    {
        $whereClause = $this->prepareWidgetConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareWidgetFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $widgets = $this->fetchAllKeyed($this->limitQueryResults(
            '
                SELECT widget.*
                ' . $joinOptions['selectFields'] . '
                FROM xf_widget AS widget
                ' . $joinOptions['joinTables'] . '
                WHERE ' . $whereClause . '
            ', $limitOptions['limit'], $limitOptions['offset']
        ), 'widget_id');

        foreach ($widgets as &$widgetRef) {
            $widgetRef['positionCodes'] = WidgetFramework_Helper_String::splitPositionCodes($widgetRef['position']);

            if (!is_array($widgetRef['options'])) {
                $widgetRef['options'] = @unserialize($widgetRef['options']);
            }
            if (empty($widgetRef['options'])) {
                $widgetRef['options'] = array();
            }

            if (!is_array($widgetRef['template_for_hooks'])) {
                $widgetRef['template_for_hooks'] = @unserialize($widgetRef['template_for_hooks']);
            }
            if (empty($widgetRef['template_for_hooks'])) {
                $widgetRef['template_for_hooks'] = array();
            }
        }

        return $widgets;
    }

    public function buildCache()
    {
        $widgets = $this->getGlobalWidgets(false, false);
        XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_KEY, $widgets);
    }

    public function prepareWidget(array &$widget)
    {
        if (empty($widget)) {
            return $widget;
        }

        $renderer = WidgetFramework_Core::getRenderer($widget['class'], false);

        if ($renderer) {
            $widget['renderer'] = &$renderer;
            $widget['rendererName'] = $renderer->getName();
            $configuration = $renderer->getConfiguration();
            $options = &$configuration['options'];
            foreach ($options as $optionKey => $optionType) {
                if (!isset($widget['options'][$optionKey])) {
                    $widget['options'][$optionKey] = '';
                }
            }
        } else {
            $widget['rendererName'] = new XenForo_Phrase('wf_unknown_renderer', array('class' => $widget['class']));
            $widget['rendererNotFound'] = true;
            $widget['active'] = false;
        }

        return $widget;
    }

    public function prepareWidgetConditions(
        /** @noinspection PhpUnusedParameterInspection */
        array $conditions, array &$fetchOptions)
    {
        $db = $this->_getDb();
        $sqlConditions = array();

        if (isset($conditions['widget_id'])) {
            if (is_array($conditions['widget_id'])
                && count($conditions['widget_id']) > 0
            ) {
                $sqlConditions[] = 'widget.widget_id IN(' . $db->quote($conditions['widget_id']) . ')';
            } else {
                $sqlConditions[] = 'widget.widget_id = ' . $db->quote($conditions['widget_id']);
            }
        }

        if (isset($conditions['widget_page_id'])) {
            if (is_array($conditions['widget_page_id'])
                && count($conditions['widget_page_id']) > 0
            ) {
                $sqlConditions[] = 'widget.widget_page_id IN(' . $db->quote($conditions['widget_page_id']) . ')';
            } else {
                $sqlConditions[] = 'widget.widget_page_id = ' . $db->quote($conditions['widget_page_id']);
            }
        }

        if (isset($conditions['group_id'])) {
            if (is_array($conditions['group_id'])
                && count($conditions['group_id']) > 0
            ) {
                $sqlConditions[] = 'widget.group_id IN(' . $db->quote($conditions['group_id']) . ')';
            } else {
                $sqlConditions[] = 'widget.group_id = ' . $db->quote($conditions['group_id']);
            }
        }

        if (isset($conditions['active'])) {
            $sqlConditions[] = 'widget.active = ' . intval($conditions['active']);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareWidgetFetchOptions(
        /** @noinspection PhpUnusedParameterInspection */
        array $fetchOptions)
    {
        $selectFields = '';
        $joinTables = '';

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }
}
