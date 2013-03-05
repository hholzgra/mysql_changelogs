#! /bin/bash

VERSIONS="5.0 5.1 5.5 5.6"

INDEX_BASE="http://dev.mysql.com/doc/relnotes/mysql"
INDEX_DOC="index.html"

NEWS_BASE="http://dev.mysql.com/doc/relnotes/mysql"

HTML_DIR="./html"

mkdir -p $HTML_DIR

for version in $VERSIONS; do

  news_files=`wget -O- -q $INDEX_BASE/$version/en/$INDEX_DOC | grep -o "news-$version-.*\.html" | grep -v "sp" | sort -g | uniq`

  for file in $news_files; do

    url="$NEWS_BASE/$version/en/$file"

    echo "fetching $url"

    wget -q -O- $url | tidy -asxml -utf8 2>/dev/null > "$HTML_DIR/$file"

  done

done
