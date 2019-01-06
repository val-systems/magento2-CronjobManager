<?php

namespace EthanYehuda\CronjobManager\Model;

use EthanYehuda\CronjobManager\Api\ScheduleManagementInterface;
use EthanYehuda\CronjobManager\Helper\Processor;
use EthanYehuda\CronjobManager\Api\ScheduleRepositoryInterface;
use Magento\Cron\Model\ConfigInterface;
use EthanYehuda\CronjobManager\Api\JobManagementInterface;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ScheduleManagement implements ScheduleManagementInterface
{
    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var ScheduleRepositoryInterface
     */
    private $scheduleRepository;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    public function __construct(
        Processor $processor,
        ScheduleRepositoryInterface $scheduleRepository,
        ConfigInterface $config,
        DateTime $dateTime,
        ScheduleFactory $scheduleFactory
    ) {
        $this->processor = $processor;
        $this->scheduleRepository = $scheduleRepository;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->scheduleFactory = $scheduleFactory;
    }

    public function execute(int $scheduleId): bool
    {
        $groups = $this->listJobs();
        $schedule = $this->scheduleRepository->get($scheduleId);
        $jobCode = $schedule->getJobCode();
        $groupId = $this->getGroupId($jobCode, $groups);
        $jobConfig = $groups[$groupId][$jobCode];

        $this->processor->runScheduledJob($jobConfig, $schedule);
        $this->scheduleRepository->save($schedule);

        return true;
    }

    public function listJobs(): array
    {
        return $this->config->getJobs();
    }

    public function createSchedule(string $jobCode, $time = null): Schedule
    {
        $time = strftime(ScheduleManagementInterface::TIME_FORMAT, $time ?? $this->dateTime->gmtTimestamp());

        $schedule = $this->scheduleFactory->create()
            ->setJobCode($jobCode)
            ->setStatus(Schedule::STATUS_PENDING)
            ->setCreatedAt(
                strftime(ScheduleManagementInterface::TIME_FORMAT, $this->dateTime->gmtTimestamp())
            )->setScheduledAt($time);

        $this->scheduleRepository->save($schedule);

        return $schedule;
    }

    public function scheduleNow(string $jobCode): Schedule
    {
        return $this->createSchedule($jobCode);
    }

    public function schedule(string $jobCode, int $time): Schedule
    {
        return $this->createSchedule($jobCode, $time);
    }

    public function getGroupId(string $jobCode, $groups = null): ?string
    {
        if (is_null($groups)) {
            $groups = $this->listJobs();
        }

        foreach ($groups as $groupId => $crons) {
            if (isset($crons[$jobCode])) {
                return $groupId;
            }
        }

        return null;
    }

    public function flush(): bool
    {
        $jobGroups = $this->listJobs();
        foreach ($jobGroups as $groupId => $crons) {
            $this->processor->cleanupJobs($groupId);
        }

        return true;
    }
}
