## Submitting useful bug reports

Please search existing issues first to make sure this is not a duplicate.
Every issue report has a cost for the developers required to field it; be
respectful of others' time and ensure your report isn't spurious prior to
submission. Please adhere to [sound bug reporting principles](http://www.chiark.greenend.org.uk/~sgtatham/bugs.html).

## Development ideology

Truths which we believe to be self-evident:

- **It's an asynchronous world.**  Be wary of anything that undermines
   async principles.

- **The answer is not more options.**  If you feel compelled to expose
   new preferences to the user it's very possible you've made a wrong
   turn somewhere.

- **There are no power users.** The idea that some users "understand"
   concepts better than others has proven to be, for the most part, false.
   If anything, "power users" are more dangerous than the rest, and we
   should avoid exposing dangerous functionality to them.

## Code style

The amphp project adheres to the PSR-2 style guide with the exception that
opening braces for classes and methods must appear on the same line as
the declaration:

https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md