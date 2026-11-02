<?php

namespace App\Livewire\Pages\LiveStreams;

use App\Actions\Api\Twitch\SearchCategories;
use App\Actions\LiveStreams\ShareLiveStream;
use App\Enums\SocialPlatform;
use App\Livewire\Concerns\HasTableHelpers;
use App\Livewire\Forms\LiveStreamTemplateForm;
use App\Livewire\Forms\StreamCategoryRuleForm;
use App\Models\LiveStream;
use App\Models\StreamCategoryRule;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Edit extends Component
{
    use HasTableHelpers;
    use WithPagination;

    public StreamCategoryRuleForm $ruleForm;

    public LiveStreamTemplateForm $templateForm;

    /**
     * @var array<string, bool>
     */
    public array $enabled = [];

    /**
     * @var array<int, array{id: string, name: string, box_art_url: string}>
     */
    public array $searchedCategories = [];

    public string $phrase = '';

    public function mount(): void
    {
        $this->sortBy = 'started_at';
        $this->perPage = 10;

        foreach (SocialPlatform::cases() as $platform) {
            $this->enabled[$platform->value] = $platform->enabled();
        }

        $this->templateForm->fillFromPlatforms();
    }

    public function render()
    {
        return view('livewire.pages.live-streams.edit', [
            'platforms' => SocialPlatform::cases(),
        ]);
    }

    #[Computed]
    public function streams()
    {
        return LiveStream::query()
            ->with('socialMediaPosts')
            ->when($this->search != '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('title', 'LIKE', "%$this->search%")
                    ->orWhere('category_name', 'LIKE', "%$this->search%")
            ))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function categoryRules()
    {
        return StreamCategoryRule::query()->orderBy('category_name')->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Shares a stream the rules skipped, or retries one that failed. Going live is
     * the only time an "I'm live" post makes sense, so ended streams are refused.
     */
    public function postNow(int $streamId, string $platform): void
    {
        $platform = SocialPlatform::tryFrom($platform) ?? abort(422);
        $stream = LiveStream::findOrFail($streamId);

        if (! $stream->isLive()) {
            Flux::toast(variant: 'danger', text: 'Only a stream that is live right now can be shared.', duration: 3000);

            return;
        }

        if (! $platform->isConnectedFor(auth()->user())) {
            Flux::toast(variant: 'danger', text: "Connect {$platform->label()} on the connections page first.", duration: 3000);

            return;
        }

        $post = (new ShareLiveStream)->shareTo($stream, $platform, auth()->user());

        if (! $post->wasSent()) {
            Flux::toast(variant: 'danger', text: "Could not post to {$platform->label()}: {$post->error}", duration: 5000);

            return;
        }

        Flux::toast(variant: 'success', text: "Posted to {$platform->label()}!", duration: 3000);
    }

    public function updatedEnabled(bool $enabled, string $platform): void
    {
        $platform = SocialPlatform::tryFrom($platform) ?? abort(422);

        $platform->setEnabled($enabled);

        Flux::toast(
            variant: 'success',
            text: $enabled
                ? "Go live posts will be sent to {$platform->label()}."
                : "Go live posts will no longer be sent to {$platform->label()}.",
            duration: 3000,
        );
    }

    public function saveTemplate(string $platform): void
    {
        $this->templateForm->save(SocialPlatform::tryFrom($platform) ?? abort(422));
    }

    public function resetTemplate(string $platform): void
    {
        $this->templateForm->restoreDefault(SocialPlatform::tryFrom($platform) ?? abort(422));
    }

    public function searchCategories(): void
    {
        $existing = $this->categoryRules->pluck('category_id')->all();

        $this->searchedCategories = (new SearchCategories)->handle(auth()->user(), $this->phrase)
            ->reject(fn (array $category): bool => in_array($category['id'], $existing, true))
            ->values()
            ->all();

        if ($this->searchedCategories === []) {
            Flux::toast(variant: 'danger', text: "No new categories found for search term: {$this->phrase}.", duration: 3000);
        }
    }

    public function selectCategory(int $index): void
    {
        $category = $this->searchedCategories[$index] ?? null;

        if (! $category) {
            Flux::toast(variant: 'danger', text: 'You can not add a category that was not in the returned list.', duration: 3000);

            return;
        }

        $this->ruleForm->fillFromSearch($category);

        $this->reset('phrase', 'searchedCategories');
    }

    public function clearSelection(): void
    {
        $this->ruleForm->reset();
    }

    public function storeRule(): void
    {
        $this->ruleForm->store();

        $this->reset('phrase', 'searchedCategories');
    }

    public function togglePlatform(int $ruleId, string $platform): void
    {
        $platform = SocialPlatform::tryFrom($platform) ?? abort(422);

        StreamCategoryRule::findOrFail($ruleId)->togglePlatform($platform);
    }

    public function destroyRule(int $ruleId): void
    {
        StreamCategoryRule::findOrFail($ruleId)->delete();

        Flux::toast(variant: 'success', text: 'Rule Removed!', duration: 3000);
    }
}
