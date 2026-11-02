<div>
    <x-slot name="header">Dashboard</x-slot>

    <div class="space-y-6 my-4">
        <div>
            <flux:heading size="lg" class="mb-2">Content</flux:heading>

            <flux:card>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a href="{{ route('panels') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.pencil-square />
                            <span>All Panels</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('3d_printing.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.cube />
                            <span>3D Printing</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('music.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.musical-note />
                            <span>Bands & Music</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('blog.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.pencil />
                            <span>Blog</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('movies.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.film />
                            <span>Movies</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('gallery.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.photo />
                            <span>Photo Manager</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('resume.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.document-text />
                            <span>Resumes</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('technologies.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.command-line />
                            <span>Technology</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('tv.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.tv />
                            <span>TV Shows</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('video_games.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.gamepad-2 />
                            <span>Video Games</span>
                        </flux:card>
                    </a>
                </div>
            </flux:card>
        </div>

        <div>
            <flux:heading size="lg" class="mb-2">Internal Tools</flux:heading>

            <flux:card>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a href="{{ route('live_streams.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.video-camera />
                            <span>Live Streams</span>
                        </flux:card>
                    </a>
                    <a href="{{ route('weather_report.edit') }}">
                        <flux:card class="flex space-x-2">
                            <flux:icon.cloud />
                            <span>Weather Report</span>
                        </flux:card>
                    </a>
                </div>
            </flux:card>
        </div>
    </div>
</div>
