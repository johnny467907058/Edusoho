define(function(require, exports, module) {
	var Notify = require('common/bootstrap-notify');
	var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);

	exports.run = function(options) {
		var $modal = $('#course-recommend-form').parents('.modal');
		
		var validator = new Validator({
            element: '#course-recommend-form',
            autoSubmit: false,
            onFormValidated: function(error, results, $form) {
                if (error) {
                    return false;
                }
                
                $.post($form.attr('action'), $form.serialize(), function(html) {
                    $modal.modal('hide');
                    Notify.success('设置推荐课程操作成功!');
                    var $tr = $(html);
					$('#' + $tr.attr('id')).replaceWith($tr);

                    if ($tr.data('sort') != null) {
                        var $tbody = $('#course-recommend-table').find('tbody'),
                            trs = $tbody.find('tr').sort(function(a,b){
                            return $(a).data('sort') - $(b).data('sort');
                        });

                        $tbody.find('tr').remove();
                        for (tr in trs) {
                            if(!isNaN(parseInt(tr,10))){
                                $(trs[tr]).appendTo($tbody);
                            }
                        }
                    }



                }).error(function(){
                    Notify.danger('设置推荐课程操作失败!');
                });
            }

        });

        validator.addItem({
            element: '[name="number"]',
            required: true,
            rule: 'integer minlength{min: 1} maxlength{max: 5}'
        });

		

	};

});

















