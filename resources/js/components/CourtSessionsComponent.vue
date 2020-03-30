<template>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="chartjs">
<!--                    {{ test }}-->
                    <b-table
                            id="table-transition-example"
                            :fields="fieldsForTable"
                            :items="itemsForTable"
                            bordered
                            :primary-key="primaryKey"
                            :tbody-transition-props="transProps"
                    >
                        <template v-slot:cell(Зал)="row">
                            <b-form-input v-on:change="changeRoomNumber($event, row.item)" v-model="row.item.Зал"/>
                        </template>
                    </b-table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: ['fields', 'items', 'route_room_number'],

        data() {
            return {
                primaryKey: 'key',
                fieldsForTable: this.fields,
                itemsForTable: this.items,
                transProps: {
                    // Transition name
                    name: 'flip-list'
                },
                intervalId: null,
                arr: [],
            };
        },
        computed: {
            channel() {
                return window.Echo.channel('courtsessions-channel');
            },
            test: function() {
                return (this.arr.length > 0) ? this.arr : this.itemsForTable
            }
        },
        methods: {
            changeRoomNumber(e, item){
                // let obj = {};
                // obj['item'] = item;
                axios.post(this.route_room_number, item).then((response) => {
                    console.log(response);
                    // setTimeout(() => loader.hide(), 1200);
                    // this.errors = [];
                }).catch(error => {
                    // loader.hide();
                    // this.errors = error.response.data.errors;
                    // if (typeof this.errors.keywords !== 'undefined') {
                    //     if (this.radio_keywords === 'buy')  {
                    //         this.error_keywords_buy = true;
                    //         this.error_keywords_sale = false;
                    //     } else {
                    //         this.error_keywords_sale = true;
                    //         this.error_keywords_buy = false;
                    //     }
                    // }
                }).then(() => {});
            },
            removeItems(){
                const length = this.itemsForTable.length;
                const now = Date.now();
                this.itemsForTable = this.itemsForTable.filter(item =>  this.parseDate(item['Час']) > now);

                // if (length !== this.itemsForTable.length) {
                //     console.log('changes!', this.itemsForTable);
                // }

                if (this.itemsForTable.length === 0) {
                    clearInterval(this.intervalId);
                }

            },
            parseDate(str) {
                const [date, time] = str.split(' ');
                const [day, month, year] = date.split('.');

                return new Date(`${year}-${month}-${day} ${time}`);
            }
        },
        mounted() {
            this.channel
                .listen('SendCourtSessionsToPusherWithQueue', (response) => {
                    console.log(response.item);
                    if ("clear" in response.item) {
                        this.itemsForTable = [];
                    }
                    this.itemsForTable.push(response.item);
                });

            this.removeItems();
            const ONE_SECOND = 1000;
            this.timer = setInterval(this.removeItems, ONE_SECOND);
        }
    }
</script>
