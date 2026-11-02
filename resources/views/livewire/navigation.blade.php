<flux:header container>
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <flux:navbar class="-mb-px max-lg:hidden">
        <flux:navbar.item icon="home" href="{{ route('welcome') }}" :current="request()->is('/')">
            Home
        </flux:navbar.item>

        @auth
            <flux:navbar.item icon="adjustments-horizontal" href="{{ route('dashboard') }}" :current="request()->is('dashboard')">
                Dashboard
            </flux:navbar.item>
        @endauth

        <flux:navbar.item icon="pencil-square" href="{{ route('prezet.index') }}" :current="request()->is('blog', 'blog/*')">
            Blog
        </flux:navbar.item>
        <flux:dropdown>
            <flux:navbar.item
                icon="briefcase"
                icon-trailing="chevron-down"
                :current="request()->is('work_history', 'education', 'technology', 'projects')"
            >
                Career
            </flux:navbar.item>

            <flux:navmenu>
                <flux:navmenu.item icon="building-office" href="{{ route('work_history') }}">
                    Work History
                </flux:navmenu.item>
                <flux:navmenu.item icon="academic-cap" href="{{ route('education') }}">
                    Education
                </flux:navmenu.item>
                <flux:navmenu.item icon="code-bracket" href="{{ route('projects') }}">
                    Projects
                </flux:navmenu.item>
                <flux:navmenu.item icon="command-line" href="{{ route('technologies') }}">
                    Technology
                </flux:navmenu.item>
                @if ($resume)
                    <flux:navmenu.item icon="document-text" href="{{ route('asset', ['slug' => $resume]) }}" target="_blank">
                        Resume
                    </flux:navmenu.item>
                @endif
            </flux:navmenu>
        </flux:dropdown>

        <flux:dropdown>
            <flux:navbar.item
                icon="fire"
                icon-trailing="chevron-down"
                :current="request()->is('board_games', 'music', 'movies', 'tv_shows', 'video_games')"
            >
                Fun Hobbies
            </flux:navbar.item>

            <flux:navmenu>
                <flux:navmenu.item icon="cube" href="{{ route('3d_printing') }}">
                    3D Printing
                </flux:navmenu.item>
                <flux:navmenu.item icon="dices" href="{{ route('board_games') }}">
                    Board Games
                </flux:navmenu.item>
                <flux:navmenu.item icon="musical-note" href="{{ route('music') }}">
                    Bands & Music
                </flux:navmenu.item>
                <flux:navmenu.item icon="film" href="{{ route('movies') }}">
                    Movies
                </flux:navmenu.item>
                <flux:navmenu.item icon="tv" href="{{  route('tv_shows') }}">
                    TV Shows
                </flux:navmenu.item>
                <flux:navmenu.item icon="photo" href="{{ route('gallery') }}">
                    Photo Gallery
                </flux:navmenu.item>
                <flux:navmenu.item icon="gamepad-2" href="{{ route('video_games') }}">
                    Video Games
                </flux:navmenu.item>
            </flux:navmenu>
        </flux:dropdown>
    </flux:navbar>

    <flux:spacer />

    <flux:button x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle" aria-label="Toggle dark mode" class="cursor-pointer" />

    @auth
        <flux:dropdown position="top" align="start">
            <flux:button class="mx-2" size="sm" icon-trailing="chevron-down">{{ auth()->user()->name }}</flux:button>

            <flux:menu>
                <flux:menu.item icon="link" href="{{ route('connections.edit') }}">
                    Connections
                </flux:menu.item>
                <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">
                    Logout
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endauth

    <flux:sidebar stashable sticky class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:navlist>
            <flux:navlist.item
                icon="home"
                href="{{ route('welcome') }}"
                :current="request()->is('/')"
            >
                Home
            </flux:navlist.item>

            @auth
                <flux:navlist.item icon="adjustments-horizontal" href="{{ route('dashboard') }}" :current="request()->is('dashboard')">
                    Dashboard
                </flux:navlist.item>
            @endauth

            <flux:navlist.item
                icon="pencil-square"
                href="{{ route('prezet.index') }}"
                :current="request()->is('blog', 'blog/*')"
            >
                Blog
            </flux:navlist.item>
            
            <flux:navlist.group
                expandable
                :expanded="false"
                heading="Career"
                icon="briefcase"
                :current="request()->is('work_history', 'education', 'technology', 'projects')"
            >
                <flux:navmenu.item icon="building-office" href="{{ route('work_history') }}">
                    Work History
                </flux:navmenu.item>
                <flux:navmenu.item icon="academic-cap" href="{{ route('education') }}">
                    Education
                </flux:navmenu.item>
                <flux:navmenu.item icon="code-bracket" href="{{ route('projects') }}">
                    Projects
                </flux:navmenu.item>
                <flux:navmenu.item icon="command-line" href="{{ route('technologies') }}">
                    Technology
                </flux:navmenu.item>
                <flux:navmenu.item icon="photo" href="{{ route('gallery') }}">
                    Photo Gallery
                </flux:navmenu.item>
                @if ($resume)
                    <flux:navmenu.item icon="document-text" href="{{ route('asset', ['slug' => $resume]) }}" target="_blank">
                        Resume
                    </flux:navmenu.item>
                @endif
            </flux:navlist.group>

            <flux:navlist.group
                expandable
                :expanded="false"
                heading="Fun Hobbies"
                icon="fire"
                :current="request()->is('board_games', 'music', 'movies', 'tv_shows', 'video_games')"
            >
                <flux:navmenu.item icon="cube" href="{{ route('3d_printing') }}">
                    3D Printing
                </flux:navmenu.item>
                <flux:navmenu.item icon="dices" href="{{ route('board_games') }}">
                    Board Games
                </flux:navmenu.item>
                <flux:navmenu.item icon="musical-note" href="{{ route('music') }}">
                    Bands & Music
                </flux:navmenu.item>
                <flux:navmenu.item icon="film" href="{{ route('movies') }}">
                    Movies
                </flux:navmenu.item>
                <flux:navmenu.item icon="tv" href="{{  route('tv_shows') }}">
                    TV Shows
                </flux:navmenu.item>
                <flux:navmenu.item icon="photo" href="{{ route('gallery') }}">
                    Photo Gallery
                </flux:navmenu.item>
                <flux:navmenu.item icon="gamepad-2" href="{{ route('video_games') }}">
                    Video Games
                </flux:navmenu.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:button x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle" aria-label="Toggle dark mode" class="cursor-pointer" />

        @auth
            <flux:dropdown position="top" align="start">
                <flux:button class="mx-2" size="sm" icon-trailing="chevron-down">{{ auth()->user()->name }}</flux:button>

                <flux:menu>
                    <flux:menu.item icon="link" href="{{ route('connections.edit') }}">
                        Connections
                    </flux:menu.item>
                    <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">
                        Logout
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endauth
    </flux:sidebar>
</flux:header>