<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Terminal;
use App\Models\User;
$admin = User::where('email','system.admin@example.test')->firstOrFail();
$organizationId = Terminal::where('code','SVT3000-01')->value('organization_id');
$terminal = Terminal::firstOrCreate(['organization_id'=>$organizationId,'code'=>'SVT3000-03'],[
    'uuid'=>(string) Illuminate\Support\Str::uuid(), 'name'=>'ترمینال آزمون FLOW-09 شماره سه', 'serial_number'=>'FLOW09-SERIAL-03',
    'manufacturer'=>'GENERIC', 'model'=>'FLOW09-TEST', 'platform'=>'Android', 'os_version'=>'7.1.2', 'app_version'=>'1.0.0',
    'status'=>'ACTIVE', 'registered_by_user_id'=>$admin->id, 'registered_at'=>now(),
]);
echo json_encode(['status'=>'READY','id'=>$terminal->id,'code'=>$terminal->code,'uuid'=>$terminal->uuid],JSON_UNESCAPED_UNICODE).PHP_EOL;
