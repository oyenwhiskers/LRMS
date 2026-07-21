<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><title>{{ $staff->staff_number }} Employee Identity</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;margin:0;padding:24px}.card{width:86mm;border:1px solid #d9d4c7;padding:8mm;box-sizing:border-box}.gold{height:3px;background:#c8a24a;margin-bottom:18px}h1{font-family:Georgia,serif;font-size:20px;margin:0 0 18px}small{display:block;color:#666;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px}.value{font-size:16px;font-weight:bold;margin-bottom:14px}.qr{display:flex;align-items:end;justify-content:space-between;border-top:1px solid #d9d4c7;padding-top:14px}.qr img{width:32mm;height:32mm}.actions{margin-bottom:18px}@media print{.actions{display:none}body{padding:0}.card{border:0}}
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print employee identity</button> <a href="{{ route('staff.index') }}">Back</a></div>
<div class="card">
    <div class="gold"></div><h1>Employee Identity</h1>
    <small>Name</small><div class="value">{{ $staff->full_name }}</div>
    <small>Staff number</small><div class="value">{{ $staff->staff_number }}</div>
    <small>Position</small><div class="value">{{ $staff->position?->name ?? 'Staff' }}</div>
    <div class="qr"><div><strong>LRMS</strong><small>Scan to identify</small></div><img src="{{ $qr }}" alt="Employee QR code"></div>
</div>
</body>
</html>
