<?php
namespace Topxia\Service\Question\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Question\QuestionService;
use Topxia\Common\ArrayToolkit;

class QuestionServiceImpl extends BaseService implements QuestionService
{
    protected $cachedJudger = array();

    protected $supportedQuestionTypes = array('choice','single_choice', 'fill', 'material', 'essay', 'determine');

    public function getQuestion($id)
    {
        return $this->getQuestionDao()->getQuestion($id);
    }

    public function findQuestionsByIds(array $ids)
    {
        return $this->getQuestionDao()->findQuestionsByIds($ids);
    }

    public function searchQuestions($conditions, $orderBy, $start, $limit)
    {
        return $this->getQuestionDao()->searchQuestions($conditions, $orderBy, $start, $limit);
    }

    public function searchQuestionsCount($conditions)
    {
        return $this->getQuestionDao()->searchQuestionsCount($conditions);
    }

    public function createQuestion($fields)
    {
        if (!in_array($fields['type'], $this->supportedQuestionTypes)) {
            throw $this->createServiceException('question type error！');
        }

        $filter = $this->createQuestionFilter($fields['type']);
        $fields = $filter->filter($fields, 'create');

        if ($fields['parentId'] > 0) {
            $parentQuestion = $this->getQuestion($fields['parentId']);
            if (empty($parentQuestion)) {
                $fields['parentId'] = 0;
            } else {
                $fields['target'] = $parentQuestion['target'];
            }
        }

        $question = $this->getQuestionDao()->addQuestion($fields);

        if ($question['parentId'] >0) {
            $subCount = $this->getQuestionDao()->findQuestionsCountByParentId($question['parentId']);
            $this->getQuestionDao()->updateQuestion($question['parentId'], array('subCount' => $subCount));
        }

        return $question;
    }

    public function updateQuestion($id, $fields)
    {
        $question = $this->getQuestion($id);
        if (empty($question)) {
            throw $this->createServiceException("Question #{$id} is not exist.");
        }

        $filter = $this->createQuestionFilter($question['type']);
        $fields = $filter->filter($fields, 'update');
        if ($question['parentId'] > 0) {
            unset($fields['target']);
        }

        return $this->getQuestionDao()->updateQuestion($id, $fields);
    }

    public function statQuestionTimes ($answers)
    {
        $ids = array_keys($answers);
        $rightIds = array();
        foreach ($answers as $questionId => $answer) {
            if ($answer['status'] == 'right'){
                array_push($rightIds, $questionId);
            }
        }
        $this->getQuizQuestionDao()->updateQuestionCountByIds($ids, 'finishedTimes');
        $this->getQuizQuestionDao()->updateQuestionCountByIds($rightIds, 'passedTimes');
    }

    public function deleteQuestion($id)
    {
        $question = $this->getQuestionDao()->getQuestion($id);
        if (empty($question)) {
            throw $this->createNotFoundException();
        }
        $this->getQuestionDao()->deleteQuestion($id);

        if ($question['subCount'] > 0) {
            $this->getQuestionDao()->deleteQuestionsByParentId($id);
        }

        if ($question['parentId'] > 0) {
            $subCount = $this->getQuestionDao()->findQuestionsCountByParentId($question['parentId']);
            $this->getQuestionDao()->updateQuestion($question['parentId'], array('subCount' => $subCount));
        }
    }

    public function judgeQuestion($id, $answer, $refreshStats = false)
    {
        $results = $this->judgeQuestions(array($id => $answers), $refreshStats);
        return $results[$id];
    }

    public function judgeQuestions(array $answers, $refreshStats = false)
    {
        $questionIds = array_keys($answers);
        $questions = $this->getQuestionDao()->findQuestionsByIds($questionIds);
        $questions = ArrayToolkit::index($questions, 'id');

        $results = array();
        foreach ($answers as $id => $answer) {
            if (empty($answer)) {
                $results[$id] = array('status' => 'noAnswer');
            } elseif (empty($questions[$id])) {
                $results[$id] = array('status' => 'notFound');
            } else {
                $question = $questions[$id];
                $judger = $this->createQuestionJudger($question['type']);
                $results[$id] = $judger->judge($question, $answer);
            }
        }

        return $results;
    }

    public function getCategory($id)
    {
        return $this->getCategoryDao()->getCategory($id);
    }

    public function findCategoriesByTarget($target, $start, $limit)
    {
        return $this->getCategoryDao()->findCategoriesByTarget($target, $start, $limit);
    }

    public function createCategory($fields)
    {   
        if (!ArrayToolkit::requireds($fields, array('name'))) {
            throw $this->createServiceException("缺少必要参数，添加类别失败");
        }

        $category['userId'] = $this->getCurrentUser()->id;
        $category['name'] = $fields['name'];
        $category['createdTime'] = time();
        $category['updatedTime'] = time();
        $category['target'] = empty($fields['target']) ? '' : $fields['target'];
        $category['seq'] = $this->getCategoryDao()->getCategorysCountByTarget($category['target'])+1;

        return $this->getCategoryDao()->addCategory($category);
    }

    public function updateCategory($id, $fields)
    {   
        if (!ArrayToolkit::requireds($fields, array('name'))) {
            throw $this->createServiceException("缺少必要参数，更新类别失败");
        }
        
        $category['name'] = $fields['name'];
        $category['updatedTime'] = time();
        return $this->getCategoryDao()->updateCategory($id, $category);
    }

    public function deleteCategory($id)
    {
        return $this->getCategoryDao()->deleteCategory($id);
    }

    public function sortCategories($target, array $sortedIds)
    {
        $categories = $this->findCategoriesByTarget($target, 0, self::MAX_CATEGORY_COUNT);
        $categories = ArrayToolkit::index($categories, 'id');
        $seq = 1;

        foreach ($sortedIds as $categoryId) {
            if (!array_key_exists($categoryId, $categories)) {
                continue;
            }

            $fields = array('seq' => $seq);
            if ($fields['seq'] != $categories[$categoryId]['seq']) {
                $this->getCategoryDao()->updateCategory($categoryId, $fields);
            }

            $seq ++;
        }
    }

    protected function createQuestionFilter($type)
    {
        switch ($type) {
            case 'choice':
            case 'single_choice':
                $name = 'Choice';
                break;
            case 'fill':
                $name = 'Fill';
                break;
            default:
                $name = 'Default';
                break;
        }
        $class = __NAMESPACE__  . "\\Filter\\{$name}Filter";
        return new $class();
    }

    protected function createQuestionJudger($type)
    {
        if (empty($this->cachedJudger[$type])) {
            switch ($type) {
                case 'choice':
                case 'single_choice':
                    $name = 'Choice';
                    break;
                case 'fill':
                    $name = 'Fill';
                    break;
                case 'determine':
                    $name = 'Determine';
                    break;
                default:
                    $name = 'Not';
                    break;
            }
            $class = __NAMESPACE__  . "\\Judger\\{$name}Judger";
            $this->cachedJudger[$type] = new $class();
        }

        return $this->cachedJudger[$type];
    }

    public function findAllFavoriteQuestionsByUserId ($id)
    {
        return $this->getQuestionFavoriteDao()->findAllFavoriteQuestionsByUserId($id);
    }

    public function findFavoriteQuestionsByUserId ($id, $start, $limit)
    {
        return $this->getQuestionFavoriteDao()->findFavoriteQuestionsByUserId($id, $start, $limit);
    }

    public function findFavoriteQuestionsCountByUserId ($id)
    {
        return $this->getQuestionFavoriteDao()->findFavoriteQuestionsCountByUserId($id);
    }

    public function favoriteQuestion($questionId, $targetType, $targetId, $userId)
    {
        $favorite = array(
            'questionId' => $questionId,
            'targetType' => $targetType,
            'targetId' => $targetId,
            'userId' => $userId,
            'createdTime' => time()
        );

        $favoriteBack = $this->getQuestionFavoriteDao()->getFavoriteByQuestionIdAndTargetAndUserId($favorite);

        if (!$favoriteBack) {
            return $this->getQuestionFavoriteDao()->addFavorite($favorite);
        }

        return $favoriteBack;
    }

    public function unFavoriteQuestion ($questionId, $targetType, $targetId, $userId)
    {
        $favorite = array(
            'questionId' => $questionId,
            'targetType' => $targetType,
            'targetId' => $targetId,
            'userId' => $userId
        );

        return $this->getQuestionFavoriteDao()->deleteFavorite($favorite);
    }

    private function getQuestionDao()
    {
        return $this->createDao('Question.QuestionDao');
    }

    private function getCategoryDao()
    {
        return $this->createDao('Question.CategoryDao');
    }

    private function getQuestionFavoriteDao()
    {
        return $this->createDao('Quiz.QuestionFavoriteDao');
    }

}