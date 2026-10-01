<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Документ') ?></title>
    <style>
        body{margin:0;background:#f3f4f6;color:#18191c;font:14px/1.45 Arial,sans-serif}.print-toolbar{padding:14px 24px;background:#fff;border-bottom:1px solid #ddd;display:flex;gap:10px}.print-toolbar a,.print-toolbar button{padding:9px 14px;border:1px solid #bbb;border-radius:7px;background:#fff;color:#111;text-decoration:none;cursor:pointer}.print-sheet{width:210mm;min-height:297mm;margin:20px auto;padding:20mm;background:#fff;box-shadow:0 8px 28px rgba(0,0,0,.12);box-sizing:border-box}h1{margin:0 0 20px;font-size:24px}h2{margin-top:28px;font-size:18px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #bbb;text-align:left;vertical-align:top}th{background:#eee}.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px}.signature{margin-top:48px;display:grid;grid-template-columns:1fr 1fr;gap:50px}.signature div{padding-top:30px;border-bottom:1px solid #333}@media print{body{background:#fff}.print-toolbar{display:none}.print-sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}@page{size:A4;margin:18mm}}
    </style>
</head>
<body>
    <div class="print-toolbar"><button type="button" onclick="window.print()">Печать</button><a href="javascript:history.back()">Назад</a></div>
    <main class="print-sheet"><?= $content ?></main>
</body>
</html>
