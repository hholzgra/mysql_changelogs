#! /bin/bash

VERSIONS="5.0 5.1 5.5 5.6 5.7"
VERSIONS="3-23 4-0 4-1"

INDEX_DOC="index.html"

INDEX_BASE="http://dev.mysql.com/doc/relnotes/mysql"
NEWS_BASE="http://dev.mysql.com/doc/relnotes/mysql"

OLD_BASE="http://dev.mysql.com/doc/refman/4.1/en/"

HTML_DIR="./html"

mkdir -p $HTML_DIR

for version in $VERSIONS; do

  news_files=`wget -O- -q $INDEX_BASE/$version/en/$INDEX_DOC | grep -o "news-$version-.*\.html" | grep -v "sp" | sort -g | uniq`

  for file in $news_files; do
    if test -s $HTML_DIR/$file
    then
      echo "$file already there"
    else
      url="$NEWS_BASE/$version/en/$file"

      echo "fetching $url"

      wget -q -O- $url | tidy -asxml -utf8 2>/dev/null > "$HTML_DIR/$file"
    fi
  done

done

for version in $OLD_VERSIONS; do

  news_files=`wget -O- -q $OLD_BASE/news-$version-x.html | grep -o "news-$version-.*\.html" | grep -v '"' | grep -v x | sort -g | uniq`

  for file in $news_files; do
    if test -s $HTML_DIR/$file
    then
      echo "$file already there"
    else
      url="$OLD_BASE/$file"

      echo "fetching $url"

      wget -q -O- $url | tidy -asxml -utf8 2>/dev/null > "$HTML_DIR/$file"
    fi
  done

done
