import { Controller } from '@hotwired/stimulus';
import * as noUiSlider from 'nouislider/dist/nouislider.min';

let routes = require('../../public/js/fos_js_routes.json');
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
Routing.setRoutingData(routes);

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['slider', 'results', 'start_year', 'end_year'];
    static values = {
        startYear: Number,
        endYear: Number,
        q: String,
    }

    connect() {
        super.connect();
        console.error();

        var slider = this.sliderTarget;

        let uislider = noUiSlider.create(slider, {
            start: [1980, 1990],
            connect: true,
            tooltips: true,
            increment: 1,
            range: {
                'min': this.startYearValue,
                'max': this.endYearValue,
            }
        });
        uislider.on('update',  (values, handle) => {

            console.log(handle, values);
            document.getElementById('startYear').value = values[0];
            document.getElementById('endYear').value = values[1];

            // this.startYearTarget.value = uislider.range[0];
        });

        // var mySlider = new RangeSlider({
        //     target: this.sliderTarget,
        //     values: [this.startYearValue, this.endYearValue],
        //     range: true,
        //     tooltip: true,
        //     scale: true,
        //     labels: true,
        //     set: [2010, 2013]
        // })
        //     .onChange(val => console.log(val));
    }

    async submitForm() {
        // see https://symfonycasts.com/screencast/stimulus/form-ajax
        const $form = this.formTarget;
        this.resultsTarget.innerHTML = await axiosg({
            url: $form.prop('action'),
            method: $form.prop('method'),
            data: $form.serialize(),
        });
    }
}
