<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Portal</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/js/app.js'])
    @else
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/src/main.jsx"></script>
    @endif
</head>
<body>
    <div id="root"></div>
</body>
</html>
