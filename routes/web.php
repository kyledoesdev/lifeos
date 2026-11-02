<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\ConnectionController;
use App\Livewire\Blog;
use App\Livewire\BlogViews;
use App\Livewire\BoardGames;
use App\Livewire\Connection;
use App\Livewire\Dashboard;
use App\Livewire\Education;
use App\Livewire\Media\Games\Edit as EditGames;
use App\Livewire\Media\Games\Show as ShowGames;
use App\Livewire\Media\Movies\Edit as EditMovies;
use App\Livewire\Media\Movies\Show as ShowMovies;
use App\Livewire\Media\Music\Edit as EditMusic;
use App\Livewire\Media\Music\Show as ShowMusic;
use App\Livewire\Media\Tv\Edit as EditTv;
use App\Livewire\Media\Tv\Show as ShowTv;
use App\Livewire\Pages\LiveStreams\Edit as EditLiveStreams;
use App\Livewire\Pages\Technologies\Edit as EditTechnologies;
use App\Livewire\Pages\Technologies\Show as ShowTechnologies;
use App\Livewire\Pages\WeatherReport\Edit as EditWeatherReport;
use App\Livewire\Panels;
use App\Livewire\Photos\Gallery;
use App\Livewire\Photos\Uploader;
use App\Livewire\Printing\Edit as EditPrinting;
use App\Livewire\Printing\Show as ShowPrinting;
use App\Livewire\Projects;
use App\Livewire\Resume;
use App\Livewire\WorkHistory;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

/* Career Views */
Route::livewire('/education', Education::class)->name('education');
Route::livewire('/projects', Projects::class)->name('projects');
Route::livewire('/technology', ShowTechnologies::class)->name('technologies');
Route::livewire('/work_history', WorkHistory::class)->name('work_history');

/* Hobby Views */
Route::livewire('/board_games', BoardGames::class)->name('board_games');
Route::livewire('/movies', ShowMovies::class)->name('movies');
Route::livewire('/music', ShowMusic::class)->name('music');
Route::livewire('/tv', ShowTv::class)->name('tv_shows');
Route::livewire('/video_games', ShowGames::class)->name('video_games');
Route::livewire('/gallery', Gallery::class)->name('gallery');
Route::livewire('/3d_printing', ShowPrinting::class)->name('3d_printing');

Route::get('/asset/{slug}', AssetController::class)->name('asset');

Route::middleware(Authenticate::class)->group(function () {
    Route::livewire('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/connect/{type}', [ConnectionController::class, 'connect'])->name('connect');
    Route::get('/connection/callback', [ConnectionController::class, 'processConnection'])->name('process_connection');
    Route::get('/connections/edit', Connection::class)->name('connections.edit');

    Route::livewire('/blog/edit', Blog::class)->name('blog.edit');
    Route::livewire('/blog/{post}/views', BlogViews::class)->name('blog.post_views');

    Route::livewire('/panels', Panels::class)->name('panels');

    Route::livewire('/3d_printing/edit', EditPrinting::class)->name('3d_printing.edit');

    Route::livewire('/gallery/edit', Uploader::class)->name('gallery.edit');
    Route::livewire('/resumes/edit', Resume::class)->name('resume.edit');

    Route::livewire('/movies/edit', EditMovies::class)->name('movies.edit');
    Route::livewire('/tv/edit', EditTv::class)->name('tv.edit');
    Route::livewire('/music/edit', EditMusic::class)->name('music.edit');
    Route::livewire('/video_games/edit', EditGames::class)->name('video_games.edit');
    Route::livewire('/technology/edit', EditTechnologies::class)->name('technologies.edit');

    Route::livewire('/weather_report/edit', EditWeatherReport::class)->name('weather_report.edit');
    Route::livewire('/live_streams/edit', EditLiveStreams::class)->name('live_streams.edit');
});

require __DIR__.'/auth.php';
require __DIR__.'/prezet.php';
