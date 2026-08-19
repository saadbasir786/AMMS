<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(App\Models\Employee::with('unit')->orderBy('id')->get() as $e){echo json_encode(['id'=>$e->id,'email'=>$e->email,'name'=>$e->full_name,'unit_id'=>$e->organizational_unit_id,'unit'=>$e->unit?->code],JSON_UNESCAPED_UNICODE),PHP_EOL;}
