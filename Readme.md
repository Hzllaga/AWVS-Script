# AWVS-Script

造个轮子，批量提交/删除任务。

```
MODE
    -u URL
        Scan with single target.
    -f File
        Scan with target list.
    -d
        Delete all targets.
    
OPTIONS
    --speed=speed
        Specify scan speed, 1(sequential) 2(slow) 3(moderate) 4(fast), default is 3.
    --proxy=host:port
        Specify scan proxy.

EXAMPLE
    php awvsscan.php -u example.com --speed=2
    php awvsscan.php -f domains.txt --speed=1 --proxy=127.0.0.1:9999
    php awvsscan.php -d
```

