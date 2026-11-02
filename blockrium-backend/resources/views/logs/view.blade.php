<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Log Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .log-container {
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
            background-color: #f4f4f4;
            padding: 10px;
            border: 1px solid #ddd;
            max-height: 400px;
            overflow-y: scroll;
        }
        .controls {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <h1>Laravel Log Viewer</h1>

    <div class="controls">
        <a href="{{ route('logs.view', 100) }}">Last 100 lines</a> |
        <a href="{{ route('logs.view', 500) }}">Last 500 lines</a> |
        <a href="{{ route('logs.view', 1000) }}">Last 1000 lines</a> |
        <a href="{{ route('logs.view', 'all') }}">All lines</a>
    </div>

    <div class="log-container">
        @foreach($linesToShow as $line)
            <p>{{ $line }}</p>
        @endforeach
    </div>

</body>
</html>
