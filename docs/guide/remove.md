# Removing the generated CRUD files

## Remove files

Laravel CRUD Generator allows you to remove the generated
files using the `artisan crud:remove` command.

```bash
$ php artisan crud:remove customers.tickets.replies
```

This will remove all the files generated by
the `crud:generate` for this table.

## Backup

If you want to keep the generated files, you can use the
`--backup` option to move the files into a Zip archive
instead.

```bash
$ php artisan crud:remove customers.tickets.replies --backup=backup.zip
```

This too will remove the files from the disk but will also
create a Zip archive of all the removed files too.

