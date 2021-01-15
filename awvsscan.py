# -*- coding: utf-8 -*-
import argparse
import hashlib
import random
import requests
import json
import tqdm
import cowsay
import threading

# Disable SSL Warning
requests.packages.urllib3.disable_warnings()
# Init a request session
req = requests.Session()
req.headers = {
    'Content-type': 'application/json; charset=utf8;'
}


def banner(text):
    cowsay.chars[random.randrange(len(cowsay.char_names))](text)


def login():
    payload = {
        'email': args.username,
        'password': hashlib.sha256(args.password.encode("utf-8")).hexdigest(),
        'remember_me': False,
        'logout_previous': True,
    }
    try:
        session_token = req.post(args.url + '/api/v1/me/login', data=json.dumps(payload), verify=False).headers[
            'X-Auth']
    except KeyError:
        print('Login Failed!')
        exit(0)
    req.headers['X-Auth'] = session_token


def add_target(address):
    payload = {
        'address': address,
        'description': '',
        'criticality': 10,
    }
    return json.loads(req.post(args.url + '/api/v1/targets', data=json.dumps(payload), verify=False).text)['target_id']


def patch_target(target_id):
    payload = {
        'scan_speed': 'fast',
        'proxy': {
            'enabled': False,
            'protocol': 'http',
            'address': '127.0.0.1',
            'port': '9150',
        }
    }
    if args.speed:
        payload['scan_speed'] = args.speed
    if args.proxy:
        proxy = args.proxy.split(':')
        payload['proxy']['enabled'] = True
        payload['proxy']['address'] = proxy[0]
        payload['proxy']['port'] = proxy[1]
    req.patch(args.url + '/api/v1/targets/' + target_id + '/configuration', data=json.dumps(payload), verify=False)


def scan_target(target_id):
    payload = {
        'target_id': target_id,
        'profile_id': '11111111-1111-1111-1111-111111111111',
        'schedule': {
            'disable': False,
            'start_date': None,
            'time_sensitive': False,
        },
        'ui_session_id': '81ae275a0a97d1a09880801a533a0ff1',
    }
    req.post(args.url + '/api/v1/scans', data=json.dumps(payload), verify=False)


def get_targets():
    return json.loads(req.get(args.url + '/api/v1/targets?l=100', verify=False).text)['targets']


def get_targets_count():
    return json.loads(req.get(args.url + '/api/v1/me/stats', verify=False).text)['targets_count']


def delete_targets(target_ids):
    payload = {
        'target_id_list': target_ids,
    }
    req.post(args.url + '/api/v1/targets/delete', data=json.dumps(payload), verify=False)


def add(target):
    target_id = add_target(target)
    if (args.speed and args.speed != 'fast') or args.proxy:
        patch_target(target_id)
    scan_target(target_id)
    # progressbar.write(f'Target {target} Added.')
    progressbar.update(1)
    semaphore.release()


if __name__ == '__main__':
    banner('AWVSScan')
    # Arguments
    parser = argparse.ArgumentParser()
    parser.add_argument("-u", "--username", help="AWVS账号", required=True)
    parser.add_argument("-p", "--password", help="AWVS密码", required=True)
    parser.add_argument("-U", "--url", help="AWVS地址", required=True)
    parser.add_argument("-m", "--mode", choices=['add', 'del'], help="模式选择", required=True)
    parser.add_argument("-t", "--thread", type=int, help="线程数, 默认5")
    parser.add_argument("-f", "--file", help="待添加的url文件")
    parser.add_argument("-s", "--speed", choices=['sequential', 'slow', 'moderate', 'fast'], help="扫描速度设置, 默认fast")
    parser.add_argument("-P", "--proxy", help="扫描代理设置, 127.0.0.1:9150")
    args = parser.parse_args()
    # Check if username or password are correct.
    login()
    if args.mode == 'add':
        if args.file:
            file = open(args.file, 'r')
            # Read file without \n
            targets = file.read().splitlines()
            file.close()
            # Declare a progress bar
            progressbar = tqdm.tqdm(total=len(targets))
            # Declare a Thread Pool
            semaphore = threading.BoundedSemaphore(args.thread if args.thread else 5)
            threads = []
            for i in range(len(targets)):
                semaphore.acquire()
                t = threading.Thread(target=add, args=(targets[i],))
                t.start()
                threads.append(t)
            # Waiting for complete
            for thread in threads:
                thread.join()
            progressbar.close()
        else:
            print('Missing [-f] argument.')
    # Delete mode
    else:
        target_all_count = get_targets_count()
        if target_all_count > 0:
            progressbar = tqdm.trange(target_all_count)
            while True:
                targets = get_targets()
                target_remaining_count = get_targets_count()
                progressbar.update(target_all_count - target_remaining_count - progressbar.n)
                if targets:
                    target_ids = []
                    for target in targets:
                        target_ids.append(target['target_id'])
                    delete_targets(target_ids)
                else:
                    progressbar.close()
                    break
        print('Delete task done.')
