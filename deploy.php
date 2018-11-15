<?php
namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'https://github.com/lianglunzhong/laravel-shop-advanced.git');
add('shared_files', []);
add('shared_dirs', []);
set('writable_dirs', []);
// 顺便把 composer 的 vendor 目录也加进来
add('copy_dirs', ['node_modules', 'vendor']);

host('119.23.58.162')
    ->user('root') // 使用 root 账号登录
    ->identityFile('~/.ssh/laravel-shop-ava-aliyun.pem') // 指定登录密钥文件路径
    ->become('www-data') // 以 www-data 身份执行命令
    ->set('deploy_path', '/var/www/laravel-shop-ava-deployer'); // 指定部署目录

// 定义一个上传 .env 文件的任务
desc('Upload .env file');
task('env:upload', function() {
    // 将本地的 .env 文件上传到代码目录的 .env
    upload('.env', '{{release_path}}/.env');
});

// 定义一个前端编译的任务
desc('Yarn');
task('deploy:yarn', function () {
    // release_path 是 Deployer 的一个内部变量，代表当前代码目录路径
    // run() 的默认超时时间是 5 分钟，而 yarn 相关的操作又比较费时，因此我们在第二个参数传入 timeout = 600，指定这个命令的超时时间是 10 分钟
    run('cd {{release_path}} && SASS_BINARY_SITE=http://npm.taobao.org/mirrors/node-sass yarn && yarn production', ['timeout' => 600]);
});

// 定义一个 执行 es:migrate 命令的任务
desc('Execute elasticsearch migrate');
task('es:migrate', function() {
    // {{bin/php}} 是 Deployer 内置的变量，是 PHP 程序的绝对路径。
    run('{{bin/php}} {{release_path}}/artisan es:migrate');
});

desc('Restart Horizon');
task('horizon:restart', function() {
    run('{{bin/php}} {{release_path}}/artisan horizon:terminate');
});

// 定义一个后置钩子，在 artisan:migrate 之后执行 es:migrate 任务
after('artisan:migrate', 'es:migrate');

// 定义一个后置钩子，在 deploy:shared 之后执行 env:update 任务
after('deploy:shared', 'env:upload');

// 定义一个后置钩子，在 deploy:vendors 之后执行 deploy:yarn 任务
after('deploy:vendors', 'deploy:yarn');

after('deploy:failed', 'deploy:unlock');
// 路由缓存
after('artisan:config:cache', 'artisan:route:cache');
before('deploy:symlink', 'artisan:migrate');
// 在 deploy:symlink 任务之后执行 horizon:restart 任务
after('deploy:symlink', 'horizon:restart');
// 在 deploy:vendors 之前调用 deploy:copy_dirs
before('deploy:vendors', 'deploy:copy_dirs');