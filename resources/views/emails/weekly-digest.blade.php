<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Weekly Digest Email</title>
</head>

<body>
    <!-- Greeting -->
    <h1>Hi {{ $user->name }}, here's your weekly summary...</h1>

    <!-- Activity sections -->
    @foreach ($activities as $action => $subjectTypes)
        <h3>{{ ucfirst($action) }}</h3>
        <ul>
            @foreach ($subjectTypes as $subjectType => $logs)
                <li>{{ $logs->count() }} {{ class_basename($subjectType) }}{{ $logs->count() > 1 ? 's' : '' }}</li>
            @endforeach
        </ul>
    @endforeach

    <!-- Call to action -->
    <p><a href="{{ url('/') }}">View Dashboard</a></p>

    <!-- Footer -->
    <p>Thanks for using the app!</p>
</body>

</html>
