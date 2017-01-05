<?php

namespace Biz\Task\Strategy;


use Biz\Task\Strategy\Impl\DefaultStrategy;
use Biz\Task\Strategy\Impl\FreeModeStrategy;
use Biz\Task\Strategy\Impl\LockModeStrategy;
use Biz\Task\Strategy\Impl\PlanStrategy;
use Codeages\Biz\Framework\Service\Exception\NotFoundException;

class StrategyContext
{
    const DEFAULT_STRATEGY = 1;
    const PLAN_STRATEGY = 0;

    private $strategy = null;

    private static $_instance = NULL;

    /**
     * 私有化默认构造方法，保证外界无法直接实例化
     */
    private function __construct()
    {
    }

    /**
     * 静态工厂方法，返还此类的唯一实例
     */
    public static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new StrategyContext();
        }

        return self::$_instance;
    }

    public function createStrategy($strategyType, $biz)
    {
        switch ($strategyType) {
            case self::PLAN_STRATEGY:
                $this->strategy = new PlanStrategy($biz);
                break;
            case self::DEFAULT_STRATEGY :
                $this->strategy = new DefaultStrategy($biz);
                break;
            default:
                throw new NotFoundException('teach method strategy does not exist');
        }
        return $this->strategy;
    }

    //任务的api策略
    public function createTask($fields)
    {
        return $this->strategy->createTask($fields);
    }

    public function updateTask($id, $fields)
    {
        return $this->strategy->updateTask($id, $fields);
    }

    public function deleteTask($task)
    {
        return $this->strategy->deleteTask($task);
    }

    public function canLearnTask($task)
    {
        return $this->strategy->canLearnTask($task);
    }

    public function getTasksRenderPage()
    {
        return $this->strategy->getTasksRenderPage();
    }

    public function getTaskItemRenderPage(){
        return $this->strategy->getTaskItemRenderPage();
    }

    public function publishTask($task)
    {
        return $this->strategy->publishTask($task);
    }

    public function unpublishTask($task)
    {
        return $this->strategy->unpublishTask($task);
    }

    //课程的api 策略
    public function prepareCourseItems($courseId, $tasks)
    {
        return $this->strategy->prepareCourseItems($courseId, $tasks);
    }

    public function sortCourseItems($courseId, array $itemIds)
    {
        return $this->strategy->sortCourseItems($courseId, $itemIds);
    }

}