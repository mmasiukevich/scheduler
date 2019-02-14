<?php

/**
 * Common scheduler implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Scheduler\Common;

use function ServiceBus\Common\datetimeInstantiator;
use ServiceBus\Common\Messages\Command;
use ServiceBus\Scheduler\Common\Exceptions\InvalidScheduledOperationExecutionDate;
use ServiceBus\Scheduler\Common\Exceptions\UnserializeCommandFailed;

/**
 * Scheduled job data
 *
 * @property-read ScheduledOperationId $id
 * @property-read Command              $command
 * @property-read \DateTimeImmutable   $date
 * @property-read bool                 $isSent
 */
final class ScheduledOperation
{
    /**
     * Identifier
     *
     * @var ScheduledOperationId
     */
    public $id;

    /**
     * Scheduled message
     *
     * @var Command
     */
    public $command;

    /**
     * Execution date
     *
     * @var \DateTimeImmutable
     */
    public $date;

    /**
     * The message was sent to the transport
     *
     * @var bool
     */
    public $isSent;

    /**
     * @param ScheduledOperationId $id
     * @param Command              $command
     * @param \DateTimeImmutable   $dateTime
     *
     * @return ScheduledOperation
     *
     * @throws \ServiceBus\Scheduler\Common\Exceptions\InvalidScheduledOperationExecutionDate
     */
    public static function new(ScheduledOperationId $id, Command $command, \DateTimeImmutable $dateTime): self
    {
        self::validateDatetime($dateTime);

        return new self($id, $command, $dateTime);
    }

    /**
     * @param array{processing_date:string, command:string, id:string, is_sent:bool} $data
     *
     * @return self
     *
     * @throws \ServiceBus\Scheduler\Common\Exceptions\EmptyScheduledOperationIdentifierNotAllowed
     * @throws \ServiceBus\Scheduler\Common\Exceptions\UnserializeCommandFailed
     * @throws \ServiceBus\Common\Exceptions\DateTime\CreateDateTimeFailed
     */
    public static function restoreFromRow(array $data): self
    {
        /** @var \DateTimeImmutable $dateTime */
        $dateTime = datetimeInstantiator($data['processing_date']);

        $serializedCommand = \base64_decode($data['command']);

        if(true === \is_string($serializedCommand))
        {
            /** @var Command|false $command */
            $command = \unserialize($serializedCommand, ['allowed_classes' => true]);

            if($command instanceof Command)
            {
                return new self(
                    ScheduledOperationId::restore($data['id']),
                    $command,
                    $dateTime,
                    (bool) $data['is_sent']
                );
            }
        }

        throw new UnserializeCommandFailed('Command deserialization error');
    }

    /**
     * @param ScheduledOperationId $id
     * @param Command              $command
     * @param \DateTimeImmutable   $dateTime
     * @param bool                 $isSent
     */
    private function __construct(ScheduledOperationId $id, Command $command, \DateTimeImmutable $dateTime, bool $isSent = false)
    {
        $this->id      = $id;
        $this->command = $command;
        $this->date    = $dateTime;
        $this->isSent  = $isSent;
    }

    /**
     * @param \DateTimeImmutable $dateTime
     *
     * @return void
     *
     * @throws \ServiceBus\Scheduler\Common\Exceptions\InvalidScheduledOperationExecutionDate
     */
    private static function validateDatetime(\DateTimeImmutable $dateTime): void
    {
        try
        {
            /** @var \DateTimeImmutable $currentDate */
            $currentDate = datetimeInstantiator('NOW');

            if($currentDate >= $dateTime)
            {
                throw new \InvalidArgumentException(
                    'The date of the scheduled task should be greater than the current one'
                );
            }
        }
        catch(\Throwable $throwable)
        {
            throw new InvalidScheduledOperationExecutionDate(
                $throwable->getMessage(), (int) $throwable->getCode(), $throwable
            );
        }
    }
}