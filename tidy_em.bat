
for /r %%f in ( *.html ) do tidy -ashtml -wrap 80 -i -m "%%f"

