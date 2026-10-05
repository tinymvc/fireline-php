@if (!is_fire_js())
    @extends('layout')
@endif

@section('title', 'Adapter page')

@section('content')
    <div x-data="{ count: 0 }">
        <h1>{{ $message }}</h1><button id="count" @click="count++" x-text="count"></button><a id="next" x-navigate
            href="/next">Next</a>
        <form x-data="{ form: $form() }" x-form method="post" action="/submit"><input name="email"><button name="intent"
                value="save">Save</button>
            <p id="error" x-text="form.firstError('email')"></p>
            <p id="message" x-text="form.message"></p>
        </form>
    </div>
@endsection

@if (is_fire_js())
    @yield('content')
@endif
