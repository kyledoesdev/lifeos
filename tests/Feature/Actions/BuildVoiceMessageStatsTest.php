<?php

use App\Actions\Discord\BuildVoiceMessageStats;
use App\Models\DiscordChannel;
use App\Models\DiscordVoiceMessage;

beforeEach(function () {
    $this->channel = DiscordChannel::factory()->create();

    $this->voice = fn (int $authorId, string $name, string $at, ?float $seconds) => DiscordVoiceMessage::factory()
        ->for($this->channel, 'channel')
        ->by($authorId, strtolower($name), $name)
        ->lasting($seconds)
        ->create(['sent_at' => $at]);
});

it('adds up voice messages per person', function () {
    ($this->voice)(1, 'Sam', '2026-01-05 10:00:00', 30.0);
    ($this->voice)(1, 'Sam', '2026-01-06 10:00:00', 10.0);
    ($this->voice)(2, 'Riley', '2026-03-01 09:00:00', 95.5);

    $stats = (new BuildVoiceMessageStats)->handle($this->channel);

    expect($stats['totals'])->toBe([
        'voice_messages' => 3,
        'seconds' => 135.5,
        'missing_durations' => 0,
        'people' => 2,
    ]);

    [$sam, $riley] = $stats['people'];

    expect($sam)->toBe([
        'author_id' => 1,
        'name' => 'Sam',
        'voice_messages' => 2,
        'seconds' => 40.0,
        'average_seconds' => 20.0,
        'longest_seconds' => 30.0,
        'slot' => 0,
    ])->and($riley)->toMatchArray(['name' => 'Riley', 'voice_messages' => 1, 'seconds' => 95.5, 'slot' => 1]);

    expect($stats['longest']['name'])->toBe('Riley')
        ->and($stats['longest']['seconds'])->toBe(95.5)
        ->and($stats['longest']['sent_at']->toDateString())->toBe('2026-03-01');
});

it('goes by the name in each person\'s latest voice message', function () {
    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->by(1, 'old', 'Old Name')->create(['message_id' => 2001]);
    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->by(1, 'sam', null)->create(['message_id' => 2002]);

    expect((new BuildVoiceMessageStats)->handle($this->channel)['people'][0]['name'])->toBe('sam');
});

it('charts each month from first to last, keeping empty ones', function () {
    ($this->voice)(1, 'Sam', '2026-01-05 10:00:00', 5.0);
    ($this->voice)(1, 'Sam', '2026-01-20 10:00:00', 5.0);
    ($this->voice)(2, 'Riley', '2026-01-21 10:00:00', 5.0);
    ($this->voice)(2, 'Riley', '2026-03-03 10:00:00', 5.0);
    ($this->voice)(2, 'Riley', '2026-03-04 10:00:00', 5.0);

    $stats = (new BuildVoiceMessageStats)->handle($this->channel);

    expect($stats['series'])->toBe([
        ['key' => 's0', 'name' => 'Riley', 'slot' => 0],
        ['key' => 's1', 'name' => 'Sam', 'slot' => 1],
    ])->and($stats['timeline'])->toBe([
        ['date' => '2026-01-01', 's0' => 1, 's1' => 2],
        ['date' => '2026-02-01', 's0' => 0, 's1' => 0],
        ['date' => '2026-03-01', 's0' => 2, 's1' => 0],
    ]);

    $weeks = (new BuildVoiceMessageStats)->handle($this->channel, 'week')['timeline'];

    expect($weeks[0])->toBe(['date' => '2026-01-05', 's0' => 0, 's1' => 1])
        ->and($weeks[2])->toBe(['date' => '2026-01-19', 's0' => 1, 's1' => 1])
        ->and(end($weeks)['date'])->toBe('2026-03-02');
});

it('folds everyone past the top six into one series', function () {
    foreach (range(1, 8) as $authorId) {
        foreach (range(1, 10 - $authorId) as $i) {
            ($this->voice)($authorId, "Person {$authorId}", '2026-01-10 10:00:00', 4.0);
        }
    }

    $stats = (new BuildVoiceMessageStats)->handle($this->channel);

    expect($stats['series'])->toHaveCount(7)
        ->and(end($stats['series']))->toBe(['key' => 'others', 'name' => 'Everyone else', 'slot' => null])
        ->and($stats['timeline'][0]['others'])->toBe(3 + 2)
        ->and($stats['people'][7]['slot'])->toBeNull();
});

it('flags voice messages that came back without a length', function () {
    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->lasting(null)->create();

    expect((new BuildVoiceMessageStats)->handle($this->channel)['totals']['missing_durations'])->toBe(1);
});

it('returns empty stats for a channel with no voice messages', function () {
    $stats = (new BuildVoiceMessageStats)->handle($this->channel);

    expect($stats['people'])->toBe([])
        ->and($stats['longest'])->toBeNull()
        ->and($stats['timeline'])->toBe([])
        ->and($stats['totals']['voice_messages'])->toBe(0);
});
