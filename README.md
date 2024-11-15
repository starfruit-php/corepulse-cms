# CorePulse CMS

## Installation

1. On your Pimcore 11 root project:

```bash
composer require corepulse/corepulse
```

2. Update `config/bundles.php` file:

```bash
return [
    ....
    ValidatorBundle\ValidatorBundle::class => ['all' => true],
    CorepulseBundle\CorepulseBundle::class => ['all' => true],
];
```

3. Install bundle:

```bash
    ./bin/console pimcore:bundle:install CorepulseBundle
```

4. Update `config/packages/security.yaml` file:

```bash
security:
    ...
    firewalls:
        corepulse_cms_api: '%corepulse_admin.api_firewall_settings%'
    ...

    access_control:
        ...
        - { path: ^/corepulse/cms/api/auth, roles: PUBLIC_ACCESS }
        - { path: ^/corepulse/cms/api/auth/logout, roles: ROLE_COREPULSE_USER }
        - { path: ^/corepulse/cms/api, roles: ROLE_COREPULSE_USER }
```

5. Setup default in Pimcore admin UI first then enjoy with https://your-domain/cms

![Setup default in Pimcore admin UI](/docs/img/setup-first.png "Setup default in Pimcore admin UI")

## Update
Run command to create or update custom database configs:

```bash
    # create tables
    ./bin/console corepulse:setup
    # update with option `--update` or `-u`
    ./bin/console corepulse:setup -u
```

## API
[See more](docs/API.md)


## Document
Full documents [here](docs)
