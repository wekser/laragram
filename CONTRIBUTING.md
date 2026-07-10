# Contributing

Thank you for considering contributing to Laragram!

We accept contributions via pull requests on [GitHub]. Please review these guidelines before submitting any pull requests.

## Guidelines

* Match the code style of the surrounding code ([PSR-12]). There is no automated style checker — keep diffs clean by hand.
* One pull request per feature (send multiple if you want to do more than one thing).
* Add tests if you've added something new (ensure that the current tests pass).
* Send a coherent commit history (make sure each individual commit in your pull request is meaningful).
* Document any change in behaviour. User-facing changes usually touch three places: the relevant page under [`wiki/`](wiki/README.md), the `README.md` if it affects the quickstart, and `CHANGELOG.md`.
* Strictly follow our [Git Commit Guidelines](#git-commit-guidelines)!
* Please remember that we follow [SemVer](http://semver.org/).

### Git Commit Guidelines

Just like [Angular](https://github.com/angular/material/blob/master/.github/CONTRIBUTING.md#-git-commit-guidelines), we have very precise rules over how our git commit messages can be formatted. This section is almost fully adapted. &copy; Angular 2018.

#### Commit Message Format

```
<type>(<scope>): <subject>
<BLANK LINE>
<body>
<BLANK LINE>
<footer>
```

> Any line of the commit message cannot be longer 100 characters!
> This allows the message to be easier to read on github as well as in various git tools.

##### Type

Must be one of the following:

* **feat:** a new feature
* **fix:** a bug fix
* **style:** changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc.)
* **refactor:** a code change that neither fixes a bug nor adds a feature
* **test:** adding missing tests
* **chore:** changes to the build process or auxiliary tools and libraries such as documentation generation

##### Scope

The scope could be anything specifying the place of the commit change.

##### Subject

The subject contains succinct description of the change:

* use the imperative, present tense: "change" not "changed" nor "changes"
* don't capitalize first letter
* no dot (.) at the end

##### Body

Just as in the **subject**, use the imperative, present tense: "change" not "changed" nor "changes" The body should include the motivation for the change and contrast this with previous behavior.

##### Footer

The footer should contain any information about **Breaking Changes** and is also the place to reference GitHub issues that this commit **Closes**.

> Breaking Changes are intended to highlight (in the ChangeLog) changes that will require community users to modify their code with this commit.

## Running Tests

Before you can run the tests, you have to install the package dependencies via [Composer](https://getcomposer.org/):

```bash
composer install
```

Then run PHPUnit:

```bash
vendor/bin/phpunit

# ...or the composer alias for the same command
composer test
```

To run a single test file, or a single test method:

```bash
vendor/bin/phpunit tests/Unit/BotRouterTest.php
vendor/bin/phpunit --filter test_find_route_matches_command_without_station_requirement
```

When you make a pull request, the tests are automatically run again by [GitHub Actions] across a matrix
of PHP `8.3`–`8.5` × Laravel `12`/`13`. Every combination is tested against both the `lowest` and the
`highest` Composer dependency versions, so a change must work at the advertised version floor — not just
the latest patch release.

[GitHub]: https://github.com/wekser/laragram/pulls
[GitHub Actions]: https://github.com/wekser/laragram/actions
[PSR-12]: https://www.php-fig.org/psr/psr-12/
