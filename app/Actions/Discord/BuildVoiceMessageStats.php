<?php

namespace App\Actions\Discord;

use App\Models\DiscordChannel;
use App\Models\DiscordVoiceMessage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class BuildVoiceMessageStats
{
    /**
     * Past this many top senders everyone is folded into one "Everyone else"
     * series, so the over time chart never runs out of readable colors.
     */
    public const int NAMED_SERIES = 6;

    /**
     * @return array{
     *     totals: array{voice_messages: int, seconds: float, missing_durations: int, people: int},
     *     people: list<array{author_id: int, name: string, voice_messages: int, seconds: float, average_seconds: float, longest_seconds: float, slot: ?int}>,
     *     longest: ?array{name: string, seconds: float, sent_at: CarbonInterface},
     *     series: list<array{key: string, name: string, slot: ?int}>,
     *     timeline: list<array<string, int|string>>,
     * }
     */
    public function handle(DiscordChannel $channel, string $period = 'month'): array
    {
        $voiceMessages = $channel->voiceMessages()
            ->orderBy('sent_at')
            ->get(['message_id', 'author_id', 'author_username', 'author_display_name', 'duration_seconds', 'sent_at']);

        $names = $this->latestNames($voiceMessages);

        $people = $voiceMessages->groupBy('author_id')
            ->map(function (Collection $messages, int $authorId) use ($names): array {
                $seconds = (float) $messages->sum('duration_seconds');

                return [
                    'author_id' => $authorId,
                    'name' => $names[$authorId],
                    'voice_messages' => $messages->count(),
                    'seconds' => $seconds,
                    'average_seconds' => $seconds / $messages->count(),
                    'longest_seconds' => (float) $messages->max('duration_seconds'),
                ];
            })
            ->sortByDesc('voice_messages')
            ->values()
            ->map(fn (array $person, int $rank): array => $person + [
                'slot' => $rank < self::NAMED_SERIES ? $rank : null,
            ]);

        $longest = $voiceMessages->sortByDesc('duration_seconds')->first();

        $series = $this->series($people);

        return [
            'totals' => [
                'voice_messages' => $voiceMessages->count(),
                'seconds' => (float) $voiceMessages->sum('duration_seconds'),
                'missing_durations' => $voiceMessages->whereNull('duration_seconds')->count(),
                'people' => $people->count(),
            ],
            'people' => $people->all(),
            'longest' => $longest ? [
                'name' => $names[$longest->author_id],
                'seconds' => (float) $longest->duration_seconds,
                'sent_at' => $longest->sent_at,
            ] : null,
            'series' => $series,
            'timeline' => $this->timeline($voiceMessages, $people, $series, $period),
        ];
    }

    /**
     * Names change over time, so each person goes by whatever they were called in their latest voice message.
     *
     * @param  Collection<int, DiscordVoiceMessage>  $voiceMessages
     * @return Collection<int, string>
     */
    private function latestNames(Collection $voiceMessages): Collection
    {
        return $voiceMessages->sortBy('message_id')
            ->mapWithKeys(fn (DiscordVoiceMessage $message): array => [$message->author_id => $message->author_name]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $people
     * @return list<array{key: string, name: string, slot: ?int}>
     */
    private function series(Collection $people): array
    {
        $series = $people->take(self::NAMED_SERIES)
            ->map(fn (array $person): array => [
                'key' => "s{$person['slot']}",
                'name' => $person['name'],
                'slot' => $person['slot'],
            ]);

        if ($people->count() > self::NAMED_SERIES) {
            $series->push(['key' => 'others', 'name' => 'Everyone else', 'slot' => null]);
        }

        return $series->values()->all();
    }

    /**
     * One row per week or month from the first voice message to the last, with
     * empty periods kept so gaps show up as gaps.
     *
     * @param  Collection<int, DiscordVoiceMessage>  $voiceMessages
     * @param  Collection<int, array<string, mixed>>  $people
     * @param  list<array{key: string, name: string, slot: ?int}>  $series
     * @return list<array<string, int|string>>
     */
    private function timeline(Collection $voiceMessages, Collection $people, array $series, string $period): array
    {
        if ($voiceMessages->isEmpty()) {
            return [];
        }

        $bucket = fn (CarbonInterface $at): CarbonImmutable => $period === 'week'
            ? $at->toImmutable()->startOfWeek(CarbonInterface::MONDAY)
            : $at->toImmutable()->startOfMonth();

        $empty = array_fill_keys(array_column($series, 'key'), 0);

        $rows = [];

        for ($at = $bucket($voiceMessages->first()->sent_at); $at <= $bucket($voiceMessages->last()->sent_at); $at = $period === 'week' ? $at->addWeek() : $at->addMonth()) {
            $rows[$at->toDateString()] = ['date' => $at->toDateString()] + $empty;
        }

        $keyByAuthor = $people->filter(fn (array $person): bool => $person['slot'] !== null)
            ->mapWithKeys(fn (array $person): array => [$person['author_id'] => "s{$person['slot']}"]);

        foreach ($voiceMessages as $message) {
            $rows[$bucket($message->sent_at)->toDateString()][$keyByAuthor[$message->author_id] ?? 'others']++;
        }

        return array_values($rows);
    }
}
