<?php

namespace AppBundle\Component\Export\Course;

use AppBundle\Common\ArrayToolkit;
use AppBundle\Component\Export\Exporter;

class OverviewStudentExporter extends Exporter
{
    public function canExport()
    {
        $user = $this->getUser();
        $tryManageCourse = $this->getCourseService()->tryManageCourse($this->parameter['courseId']);

        return $user->isAdmin() || $tryManageCourse;
    }

    public function getCount()
    {
        return $this->getCourseMemberService()->countMembers($this->conditions);
    }

    public function getTitles()
    {
        $titles = array('学员详情', '联系方式', '完成度');
        $tasks = $this->getTaskService()->searchTasks(
            array(
                'courseId' => $this->parameter['courseId'],
                'isOptional' => 0,
                'status' => 'published',
            ),
            array('seq' => 'ASC'),
            0,
            PHP_INT_MAX
        );
        $taskTitles = ArrayToolkit::column($tasks, 'title');

        return array_merge($titles, $taskTitles);
    }

    public function getContent($start, $limit)
    {
        $course = $this->getCourseService()->getCourse($this->parameter['courseId']);

        $members = $this->getCourseMemberService()->searchMembers(
            $this->conditions,
            $this->parameter['orderBy'],
            $start,
            $limit
        );

        $userIds = ArrayToolkit::column($members, 'userId');

        list($users, $tasks, $taskResults) = $this->getReportService()->getStudentDetail($course['id'], $userIds);
        $userProfiles = $this->getUserService()->findUserProfilesByIds($userIds);

        $datas = array();

        $status = array(
            'finish' => '已完成',
            'start' => '学习中',
        );

        foreach ($members as $member) {
            $userTaskResults = !empty($taskResults[$member['userId']]) ? $taskResults[$member['userId']] : array();

            $user = $users[$member['userId']];
            $data = array();
            $data[] = $user['nickname'];
            $data[] = empty($user['verifiedMobile']) ? $userProfiles[$user['id']]['mobile'] : $user['verifiedMobile'];

            $learnProccess = (empty($member['learnedCompulsoryTaskNum']) || empty($course['compulsoryTaskNum'])) ? 0 : (int) ($member['learnedCompulsoryTaskNum'] * 100 / $course['compulsoryTaskNum']);
            $data[] = $learnProccess > 100 ? '100%' : $learnProccess.'%';

            foreach ($tasks as $task) {
                $taskResult = !empty($userTaskResults[$task['id']]) ? $userTaskResults[$task['id']] : array();
                $data[] = empty($taskResult) ? '未开始' : $status[$taskResult['status']];
            }

            $datas[] = $data;
        }

        return $datas;
    }

    public function buildParameter($conditions)
    {
        $parameter = parent::buildParameter($conditions);
        $parameter['courseId'] = $conditions['courseId'];
        $parameter['orderBy'] = $this->getReportService()->buildStudentDetailOrderBy($conditions);

        return $parameter;
    }

    public function buildCondition($conditions)
    {
        return $this->getReportService()->buildStudentDetailConditions($conditions, $conditions['courseId']);
    }

    protected function getReportService()
    {
        return $this->getBiz()->service('Course:ReportService');
    }

    /**
     * @return TaskService
     */
    protected function getTaskService()
    {
        return $this->getBiz()->service('Task:TaskService');
    }

    /**
     * @return CourseService
     */
    protected function getCourseService()
    {
        return $this->getBiz()->service('Course:CourseService');
    }

    protected function getCourseMemberService()
    {
        return $this->getBiz()->service('Course:MemberService');
    }
}