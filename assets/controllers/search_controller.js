import {Controller} from '@hotwired/stimulus';
import * as noUiSlider from 'nouislider/dist/nouislider.min';
import axios from "axios";
// let routes = require('../../public/js/fos_js_routes.json');
// import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
// Routing.setRoutingData(routes);

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['slider', 'results', 'start_year', 'end_year', 'q'];
    static values = {
        startYear: Number,
        endYear: Number,
        q: String,
        endpoint: String
    }

    connect() {
        super.connect();
        console.error();

        var slider = this.sliderTarget;

        let uislider = noUiSlider.create(slider, {
            start: [1800, 2022],
            connect: true,
            tooltips: true,
            increment: 1,
            range: {
                'min': this.startYearValue,
                'max': this.endYearValue,
            }
        });
        uislider.on('update', (values, handle) => {
            console.log(values);
            document.getElementById('startYear').value = values[0];
            document.getElementById('endYear').value = values[1];

            // better would be to submit the entire form, not each element.
            axios.get(this.endpointValue, {
                params: {
                    startYear: values[0],
                    endYear: values[1],
                    q: this.qTarget.value
                }
            })
                .then((response) => {
                        // handle success
                        this.resultsTarget.innerHTML = response.data;
                    }
                );

        });
    }

    async submitForm() {
        // see https://symfonycasts.com/screencast/stimulus/form-ajax
        const $form = this.formTarget;
    }
}
