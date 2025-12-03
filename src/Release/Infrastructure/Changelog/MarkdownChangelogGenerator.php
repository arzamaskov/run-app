<?php

declare(strict_types=1);

namespace RunTracker\Release\Infrastructure\Changelog;

use RunTracker\Release\Application\Port\ChangelogGenerator;
use RunTracker\Release\Domain\ValueObject\Version;

final class MarkdownChangelogGenerator implements ChangelogGenerator
{
    public function generate(array $commits, Version $version): string
    {
        $date = date('Y-m-d');

        $versionString = ltrim($version->toString(), 'vV');

        $entry = "## [{$versionString}] - {$date}\n\n";

        // 4. Сгруппировать коммиты
        $grouped = $this->groupCommitsByType($commits);

        // 5. Сформировать секции
        foreach ($grouped as $type => $items) {
            if (! empty($items)) {
                $entry .= "### {$type}\n\n";
                foreach ($items as $commit) {
                    $entry .= "- {$commit['message']} ([{$commit['hash']}])\n";
                }
                $entry .= "\n";
            }
        }

        return $entry;
    }

    private function groupCommitsByType(array $commits): array
    {
        $grouped = [
            'Added' => [],
            'Changed' => [],
            'Fixed' => [],
            'Removed' => [],
            'Security' => [],
            'Other' => [],
        ];

        foreach ($commits as $commit) {
            $message = $commit['message'];

            if (preg_match('/^(feat|feature|add)(\(.*?\))?:/i', $message)) {
                $grouped['Added'][] = $commit;
            } elseif (preg_match('/^(fix|bug)(\(.*?\))?:/i', $message)) {
                $grouped['Fixed'][] = $commit;
            } elseif (preg_match('/^(change|update|refactor)(\(.*?\))?:/i', $message)) {
                $grouped['Changed'][] = $commit;
            } elseif (preg_match('/^(remove|delete)(\(.*?\))?:/i', $message)) {
                $grouped['Removed'][] = $commit;
            } elseif (preg_match('/^(security|sec)(\(.*?\))?:/i', $message)) {
                $grouped['Security'][] = $commit;
            } else {
                $grouped['Other'][] = $commit;
            }
        }

        return $grouped;
    }
}
