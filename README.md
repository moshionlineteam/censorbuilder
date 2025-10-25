# censorbuilder

our fork of [BanBuilder](https://github.com/snipe/banbuilder)

we don't need multiple languages (for now), want a 'json' file instead of language files in '.php', more flexible regexs to be in json.

```json
[
     "d[i1!]+[c<]+k+",
     "fuck"
]
```
result :

```php
Array ( [orig] => i love d111iickkkss lol [clean] => i love **********ss lol [matched] => Array ( [0] => d111iickkk ) ) 
```