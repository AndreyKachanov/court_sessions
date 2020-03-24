@extends('layouts.app')

@section('content')
    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab">
            <court_sessions-component
                :fields="{{ $fields }}"
                :items="{{ $items }}"
                route_room_number="{{ route('change_room_number') }}"
            >
            </court_sessions-component>
        </div>
    </div>
@endsection
