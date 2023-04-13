# https://unix.stackexchange.com/questions/705445/expanding-an-argument-within-single-quotes/705451#705451
#link local bundle
ORG=${2:-survos}
DIR=${3:-/home/tac/g/survos/survos/packages}
P="$DIR/$1-bundle"
echo $P
[ ! -d $P ] && echo "Directory $P DOES NOT exists." && exit 1

V='{"type": "path", "url": "/'
V+=$P
V+='" }'

lb() {
  composer config "repositories.$1" '
    {
      "type": "path",
      "url": "$P"
    }'
}
composer config repositories.$1 "$V"
composer req $ORG/$1-bundle:*@dev -W
