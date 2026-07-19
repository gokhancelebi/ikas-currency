<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

$queries = [
    'updateProduct' => 'query { __type(name:"UpdateProductInput") { inputFields { name } } }',
    'updateVariantPrices' => 'query { __type(name:"UpdateVariantPricesInput") { inputFields { name type { name kind ofType { name } } } } }',
    'variantPriceInput' => 'query { __type(name:"VariantPriceInput") { inputFields { name } } }',
    'listCategory' => 'query { listCategory { id name isAutomated } }',
    'mutations' => 'query { __schema { mutationType { fields { name } } } }',
];

foreach ($queries as $name => $query) {
    $r = $client->request(['query' => $query]);
    file_put_contents(__DIR__."/storage/app/ikas-sync/probe2-{$name}.json", json_encode($r, JSON_PRETTY_PRINT));
    echo $name.": done\n";
}
