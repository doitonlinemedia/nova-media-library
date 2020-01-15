export default {
    data() {
        return {
            showUpFolder: false,
            currentFolder: '',
            upFolder: '',
        }
    },

    mounted() {
        this.showHideFolder();
    },

    methods: {
        showHideFolder() {
            this.currentFolder = this.getCookie('media-library-folder');
            if (this.currentFolder.length > 0) {
                this.showUpFolder = true;
            } else {
                this.showUpFolder = false;
            }

            this.upFolder = this.currentFolder.substring(0, this.currentFolder.lastIndexOf('/'));

            console.log(this.upFolder);
        },

        getCookie(cname) {
            var name = cname + "=";
            var decodedCookie = decodeURIComponent(document.cookie);
            var ca = decodedCookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return "";
        },


        bg(item) {
            return 'image' === item.mime ? {backgroundImage: `url(${item.url})`} : {};
        },
        mime(item) {
            switch (item.mime) {
                case 'image':
                    return 'image';
                case 'audio':
                    return 'audio';
                case 'video':
                    return 'video';
                case 'folder':
                    return 'folder';
                default:
                    return 'file';
            }
        },
        clickCard(item) {
            if (item.type === 'folder') {
                let date = new Date();
                document.cookie = 'media-library-folder=' + item.path + '; expires=Fri, 19 Jun ' + (date.getFullYear() + 1) + ' 20:47:11 UTC; path=/';

                this.$parent.clearData();
                this.$parent.get();

                this.showHideFolder();
            } else {

                if (this.$parent.bulk.is) {
                    if (this.$parent.bulk.array.includes(item.id)) {
                        this.$parent.bulk.array = this.$parent.bulk.array.filter(id => id !== item.id).slice();
                    } else {
                        this.$parent.bulk.array.push(item.id);
                    }
                } else {
                    if (!this.$parent.tool) {
                        Nova.$emit('nml-select-file', [this.$parent.field, item.url]);
                        return;
                    }
                    this.$parent.popup = item;
                    this.$parent.popupType = 'info';
                }
            }
        }
    }
}
