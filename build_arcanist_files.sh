# !/bin/bash

# Uses `arc liberate` to update/build the library files used by arcanist

# First the extensions folder for installs that just want to load all extensions in one load option
./vendor/phorgeit/arcanist/bin/arc liberate ./extensions

# Now loop on every folder in the extensions directory
for d in ./extensions/*; do
    if [ -d "$d" ]; then
        ./vendor/phorgeit/arcanist/bin/arc liberate $d
    fi
done
