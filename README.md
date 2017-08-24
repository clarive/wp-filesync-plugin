# FileSync Wordpress Plugin

Synchonizes database contents with your file system. And vice versa.

This plugin is ALPHA!

# How does it work

It runs through the database, dumps rows and copies files from outside,
scattered directories, into a "repository" -- which is a directory of your
choosing that ideally, will be versioned with git and contain a `.git` folder
underneath.

Content that is currently being dumped and/or copied to the repository:

- Everything in the WP_POSTS and WP_POSTMETA MySQL tables
- `wp-content/uploads` files

If you have any other directories that you'd like to synchronize and not
covered by the plugin... I recommend using symbolic links to link them into
your repository folder:

   ln -s /external/dir /my/repodir/

# Guide

The plugin is mostly WP-CLI command-line subcommands created under the `wp fs`
command namespace.

For a list of commands available type:

    wp help fs

## Basic usage

Before getting started, create a directory and init `git`:

    mkdir /my/repodir && cd /my/repodir git init

To dump the database and uploaded files into a home directiory, called
repository, type:

    wp fs sync /my/repodir/

Then look through the files that have been created and add them to Git
accordingly. These are some of the most important ones:

    git add posts pages uploads themes

Commit (and push it out if your remote is set):

    git commit -m 'initial load'

## Other Options

For the current working directory, try:

    wp fs sync .

Once synced, you probably want to `git add` and `git commit` your files.  But
run `git status` for a preview of what has changed.

To update a dumped post file after data changed in Wordpress (through the admin
interface), use `dump-file`:

    wp fs dump-file ./posts/mypost.html

To upload your file changes into Wordpress, use the following:

    wp fs load-file ./posts/mypost.html

To upload the full repository contents:

    wp fs load .

To sync only files or folders matching a pattern:

    wp fs sync . --grep=/posts/ wp fs load . --grep=/posts/ wp fs load .
    --grep='/blog-.*-news.html/'

The `--grep` pattern is a ("preg") regular expression that must start and end
with a slash '/'. Use quotes and escaping accordingly.

## File Contents

Every file dumped from the database contains metadata and content in one file.

The first section of the file is known as **YAML front matter**.

The second section is the contents (if available). Some files don't have
content, just metadata.

Thus the general structure of a file (be it .html or .yml) is as following:

    --- (YAML front matter) --- (HTML or other post contents)

The most representative are posts and pages, which always have YAML metadata
AND content. Other Wordpress files may only have YAML metadata.

## File names and directory structure

Pages and posts will be created with the `.html` extension.

Uploads have their extension unchanged.

All other files will have `.yml` as extension.

Folder names are based on the `post_type` column in the Wordpress database.

## Whitespace and line-endings cleanup

With every dump / upload, tabs are changed into whitespace and Windows format
line-endings are replaced with Unix line endings.

To prevent such behaviour, use the `--no-cleanup` option.

## Modified dates

Every time something is `load`ed into Wordpress, modified dates are updated
accordingly.

If you don't want so, use `--keep-date`, which will preserve the
`post_modified` dates from the YAML.

    wp fs dump . --keep-date

## File refresh after insert

Every time a new file is found in the directory it will be inserted into the
database.

Once inserted, Wordpress assigns the file an ID.  This ID is inserted into the
file header YAML and the file is updated.

If you want to prevent the Filesync plugin from updating the file during a
`load` or `sync` operation, use the `--no-refresh` option.

    wp fs load . --no-refresh

**Caveat**: if you repeat the command, inserted files will be inserted more
than once, since Filesync does not know the ID. In that case, please update
the front matter YAML by hand.
