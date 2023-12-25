
- [演算子の優先順位] (https://www.php.net/manual/ja/language.operators.precedence.php)

PHP 8.0から`.`演算子の優先順位が変わっている。

```console
$ docker run --rm php:8.0-alpine -r 'echo "hello: " . 1 + 2;'
$ docker run --rm php:7.4-alpine -r 'echo "hello: " . 1 + 2;'
```

文字列と数値の足し算は文字列がCのatoiみたいな感じで変換されて足されるよう。
