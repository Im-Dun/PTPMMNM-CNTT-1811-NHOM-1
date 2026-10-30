<?php

declare(strict_types=1);

function schedule_next_run(array $schedule, DateTimeImmutable $from): DateTimeImmutable
{
    $frequency = (string) $schedule['frequency'];
    $time = (string) $schedule['run_time'];
    [$hour, $minute] = array_map('intval', explode(':', $time));
    $candidate = $from->setTime($hour, $minute, 0);

    if ($frequency === 'hourly') {
        $candidate = $from->setTime((int) $from->format('H'), $minute, 0);
        return $candidate <= $from ? $candidate->modify('+1 hour') : $candidate;
    }
    if ($frequency === 'daily') {
        return $candidate <= $from ? $candidate->modify('+1 day') : $candidate;
    }
    if ($frequency === 'weekly') {
        $day = (int) ($schedule['day_of_week'] ?? 0);
        $daysSinceSunday = (int) $from->format('w');
        $candidate = $candidate->modify('-' . $daysSinceSunday . ' days')->modify('+' . $day . ' days');
        return $candidate <= $from ? $candidate->modify('+1 week') : $candidate;
    }

    $day = max(1, min(31, (int) ($schedule['day_of_month'] ?? 1)));
    $daysInMonth = (int) $from->format('t');
    $candidate = $candidate->setDate(
        (int) $from->format('Y'),
        (int) $from->format('m'),
        min($day, $daysInMonth)
    );
    if ($candidate <= $from) {
        $nextMonth = $from->modify('first day of next month');
        $candidate = $nextMonth->setTime($hour, $minute, 0)->setDate(
            (int) $nextMonth->format('Y'),
            (int) $nextMonth->format('m'),
            min($day, (int) $nextMonth->format('t'))
        );
        return $candidate;
    }
    return $candidate;
}

function create_notification(PDO $pdo, int $userId, int $scheduleId, string $type, string $title, string $message): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications(user_id,schedule_id,type,title,message) VALUES(?,?,?,?,?)'
    );
    $stmt->execute([$userId, $scheduleId, $type, $title, $message]);
}

function run_due_schedules(PDO $pdo, array $config, ?DateTimeImmutable $now = null): int
{
    $now ??= new DateTimeImmutable('now');
    $due = $pdo->prepare(
        'SELECT s.*,p.source_url,p.title,p.library_id
         FROM update_schedules s
         JOIN saved_pages p ON p.id=s.saved_page_id
         WHERE s.enabled=1 AND s.next_run_at<=?
         ORDER BY s.next_run_at
         LIMIT 20'
    );
    $due->execute([$now->format('Y-m-d H:i:s')]);
    $processed = 0;

    foreach ($due->fetchAll() as $schedule) {
        $scheduleId = (int) $schedule['id'];
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT * FROM update_schedules WHERE id=? AND enabled=1 FOR UPDATE');
            $lock->execute([$scheduleId]);
            $locked = $lock->fetch();
            if (!$locked || (string) $locked['next_run_at'] > $now->format('Y-m-d H:i:s')) {
                $pdo->rollBack();
                continue;
            }

            try {
                store_snapshot($pdo, $config, (int) $locked['saved_page_id'], (string) $schedule['source_url']);
                $next = schedule_next_run($locked, $now);
                $update = $pdo->prepare(
                    "UPDATE update_schedules
                     SET last_run_at=?,last_status='success',last_error=NULL,next_run_at=?
                     WHERE id=?"
                );
                $update->execute([$now->format('Y-m-d H:i:s'), $next->format('Y-m-d H:i:s'), $scheduleId]);
                if ((int) $locked['notify_success'] === 1) {
                    create_notification(
                        $pdo,
                        (int) $locked['created_by'],
                        $scheduleId,
                        'success',
                        'Cập nhật version thành công',
                        'Trang "' . (string) $schedule['title'] . '" đã được lưu phiên bản mới.'
                    );
                }
            } catch (Throwable $error) {
                $next = schedule_next_run($locked, $now);
                $update = $pdo->prepare(
                    "UPDATE update_schedules
                     SET last_run_at=?,last_status='failed',last_error=?,next_run_at=?
                     WHERE id=?"
                );
                $update->execute([
                    $now->format('Y-m-d H:i:s'),
                    mb_substr($error->getMessage(), 0, 1000),
                    $next->format('Y-m-d H:i:s'),
                    $scheduleId,
                ]);
                if ((int) $locked['notify_failure'] === 1) {
                    create_notification(
                        $pdo,
                        (int) $locked['created_by'],
                        $scheduleId,
                        'failure',
                        'Cập nhật version thất bại',
                        'Không thể cập nhật "' . (string) $schedule['title'] . '": ' . $error->getMessage()
                    );
                }
            }
            $pdo->commit();
            $processed++;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Schedule ' . $scheduleId . ' failed: ' . $error->getMessage());
        }
    }

    return $processed;
}
