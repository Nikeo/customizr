module.exports = {
	options: {
		separator: ';',
	},
	front_js: {
		src: ['<%= paths.front_js %>parts/params-dev-mode.js', '<%= paths.front_js %>parts/bootstrap.js', '<%= paths.front_js %>fancybox/jquery.fancybox-1.3.4.min.js', '<%= paths.front_js %>parts/main.js'],
		dest: '<%= paths.front_js %>tc-scripts.js',
	},
	admin_control_js:{
		src: ['<%= paths.admin_js %>lib/icheck.min.js', '<%= paths.admin_js %>lib/selecter.min.js', '<%= paths.admin_js %>lib/stepper.min.js', '<%= paths.admin_js %>lib/select2.min.js', '<%= paths.admin_js %>_control.js', '<%= paths.admin_js %>_call_to_actions.js' , '<%= paths.admin_js %>_various_dom_ready.js'],
		dest: '<%= paths.admin_js %>theme-customizer-control.js',
	}
};