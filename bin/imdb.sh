set -x
fn=${1:-title.akas.tsv.gz}   # Defaults to /tmp dir.

curl "https://datasets.imdbws.com/$fn" --output data/$fn
cd data
gzip -d $fn
cd ..
