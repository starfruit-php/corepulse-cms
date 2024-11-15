# INDEXING

## Setup

Create tables:

    ```bash
    # create tables
        ./bin/console corepulse:setup
    # update with option `--update` or `-u`
        ./bin/console corepulse:setup -u
    ```

## Re-Generate

After updating or deteting object or document/page, automatically regenerate sitemap file with config in `corepulse`, see [example config](../config/pimcore/corepulse.yaml)

Config indexing automation
```bash
corepulse:
    event_listener:
        update_indexing: true # default false
        inspection_index: true # default false
```
## [Config Service account](https://developers.google.com/search/apis/indexing-api/v3/prereqs?hl=en)