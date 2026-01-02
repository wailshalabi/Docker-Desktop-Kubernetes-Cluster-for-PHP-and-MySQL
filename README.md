# Docker-Desktop-Kubernetes-Cluster-for-PHP-and-MySQL

![Docker](https://img.shields.io/badge/Docker-Desktop-blue?logo=docker)
![Kubernetes](https://img.shields.io/badge/Kubernetes-kind-blue?logo=kubernetes)
![MySQL](https://img.shields.io/badge/MySQL-InnoDB%20Cluster-orange?logo=mysql)
![PHP](https://img.shields.io/badge/PHP-8.x-purple?logo=php)
![License](https://img.shields.io/github/license/wailshalabi/Docker-Desktop-Kubernetes-Cluster-for-PHP-and-MySQL)

This project demonstrates:
- PHP API running with 3 replicas (high availability at app layer)
- MySQL InnoDB Cluster (official operator) with 3 instances (HA at DB layer)
- Stable DB hostname for PHP: `mycluster`
- NodePort for PHP: http://localhost:30080

> Note: kind runs everything on one physical machine (your PC). It is great for learning Kubernetes HA behavior
> (pod deletion, node drain, leader election), but it is not multi-datacenter HA.

## Requirements (Windows)
- Docker Desktop running
- kubectl installed
- kind installed

Check:

```powershell
docker version
kubectl version --client
kind version
```

## Files Structure

```powershell
php-mysql-innodb-ha-poc/
├─ README.md                     # Tutorial & proof-of-concept guide
├─ kind/
│  └─ kind-ha.yaml               # kind cluster (3 workers + NodePort mapping)
├─ php/
│  ├─ Dockerfile                 # Minimal PHP + PDO MySQL
│  └─ index.php                  # PHP app with /db endpoint
└─ k8s/
   ├─ 00-namespace.yaml          # Namespace (poc)
   ├─ 20-mysql-innodb-secret.yaml# MySQL credentials
   ├─ 21-mysql-innodbcluster.yaml# InnoDB Cluster (3 instances)
   ├─ 30-php-db-secret.yaml      # PHP DB env vars
   └─ 31-php-deploy-service.yaml # PHP Deployment + NodePort
```

## Setup Clusters

### 1) Create cluster
```powershell
kind create cluster --name ha-test --config kind\kind-ha.yaml
```

### 2) Build PHP image

```powershell
docker build -t php-ha:1.0 .\php
docker save -o artifacts\images\php-ha_1.0.tar php-ha:1.0
kind load image-archive artifacts\images\php-ha_1.0.tar --name ha-test
```

### 3) Install MySQL operator (one time)

```powershell
kubectl apply -f k8s/mysql-operator/deploy-crds.yaml
kubectl apply -f k8s/mysql-operator/deploy-operator.yaml
```

Verify

```powershell
kubectl get pods -n mysql-operator
```

## Docker image artifacts

This project stores exported Docker images in:

artifacts/images/

This folder is ignored by Git and should never be committed.
It is used only to load images into kind clusters during local development.

Example for PHP image

    docker save -o artifacts/images/php-ha_1.0.tar php-ha:1.0
    kind load image-archive artifacts/images/php-ha_1.0.tar --name ha-test

Example for MySQL offline mode

    docker pull container-registry.oracle.com/mysql/community-server:9.5.0
    docker save -o artifacts\images\mysql-innodb-image.tar container-registry.oracle.com/mysql/community-server:9.5.0
    kind load image-archive artifacts\images\mysql-innodb-image.tar --name ha-test

Then delete the stuck pods so they restart and use the now-available image automatically:

    kubectl delete pod -n poc mycluster-0 mycluster-1 mycluster-2
    kubectl get pods -n poc -w

Example for MySQL Router offline mode

    docker pull container-registry.oracle.com/mysql/community-router:9.5.0
    docker save -o artifacts\images\mysql-router-image.tar container-registry.oracle.com/mysql/community-router:9.5.0
    kind load image-archive artifacts\images\mysql-router-image.tar --name ha-test

Then delete the stuck pods so they restart and use the now-available image automatically

Verify images exist INSIDE kind nodes

    docker exec -it ha-test-control-plane crictl images | findstr mysql

### 4) Deploy everything

```powershell
kubectl apply -f k8s\00-namespace.yaml
kubectl apply -n poc -f k8s\20-mysql-innodb-secret.yaml
kubectl apply -n poc -f k8s\21-mysql-innodbcluster.yaml
kubectl apply -n poc -f k8s\30-php-db-secret.yaml
kubectl apply -n poc -f k8s\31-php-deploy-service.yaml
```

Verify

```powershell
kubectl get pods -n poc
kubectl get svc -n poc
```

## MySQL Operator installation (offline)

This project vendors the official MySQL Operator manifests locally.

Install the operator using local files:

kubectl apply -f k8s/mysql-operator/deploy-crds.yaml
kubectl apply -f k8s/mysql-operator/deploy-operator.yaml

These files are committed to the repository to allow:
- offline usage
- deterministic installs
- CI/CD without external dependencies

Verify operator is installed

```powershell
kubectl get crd | findstr mysql
kubectl get pods -n mysql-operator
```

## Delete the full cluster and verify the deletion

```powershell
kind delete cluster --name ha-test
kind get clusters
```

### Trouble Shooting

### Stabilize the API server (fix TLS handshake timeouts)

    docker restart ha-test-control-plane
    kubectl get nodes

### Restart router by deletion

    kubectl -n poc delete pod -l component=mysqlrouter

### Get pods and Volumes

    kubectl -n poc get pods
    kubectl -n poc get pvc

### Delete MySQL Pod

    kubectl -n poc delete pod mysql-0

### Restart php

    kubectl -n poc rollout restart deployment php-ha

### Step by step installation after donloading the images locally

    kind delete cluster --name ha-test
    kind create cluster --name ha-test --config kind\kind-ha.yaml
    kind export kubeconfig --name ha-test

    kind load image-archive artifacts\images\mysql-innodb-image.tar --name ha-test
    kind load image-archive artifacts\images\mysql-router-image.tar --name ha-test
    kind load image-archive artifacts\images\php-ha_1.0.tar --name ha-test

    kubectl apply -f k8s\mysql-operator\deploy-crds.yaml
    kubectl apply -f k8s\mysql-operator\deploy-operator.yaml

    kubectl apply -f k8s\00-namespace.yaml
    kubectl -n poc apply -f k8s\20-mysql-innodb-secret.yaml
    kubectl -n poc apply -f k8s\21-mysql-innodbcluster.yaml
    kubectl -n poc apply -f k8s\30-php-db-secret.yaml
    kubectl -n poc apply -f k8s\31-php-deploy-service.yaml

### Simple setup

This setup is designed to be stable on Windows / Docker Desktop:
- No MySQL Operator
- No MySQL Router
- Persistent MySQL storage (PVC)
- Minimal pods: 1x MySQL + 1x PHP
- Works with `kubectl` on `docker-desktop` context

Step by step using a local image

    kubectl apply -f k8s-simple\00-namespace.yaml
    kubectl apply -f k8s-simple\10-mysql-persistent.yaml
    kind load image-archive artifacts\images\php-ha_1.0.tar --name desktop
    kubectl apply -f k8s-simple\20-php.yaml
    kubectl -n poc port-forward svc/php-ha-nodeport 30080:80

Open:

    http://localhost:30080/
    http://localhost:30080/db

## Security disclaimer
This project is for educational purposes only, do not use this implementation directly in production Real systems.