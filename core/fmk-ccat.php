<?php
//Models contain the underlying data of each views.

//A model is an object instantiated by the collection class.
//Before instantiation, the collection class has made sure that the model has at least :
//1) a unique id
//2) a hook
//3) a priority set.

//Once alive, a model's job is :
//- instantiate its view. The model must check if its related view has to be instantiated with the default view class or a child of it.
//- assign the view to its rendering hook


if ( ! class_exists( 'CZR_Model' ) ) :
  #[\AllowDynamicProperties]
  class CZR_Model {
    static $instance;

    //the model properties
    //each view will inherit those properties
    public $hook = "";//this is the default hook declared in the index.php template
    public $render = false;
    public $view_instance;
    public $id = "";
    public $model_class = false;
    public $query = false;
    public $priority = 10;
    public $template = "";
    public $element_tag;
    public $element_id;
    public $element_class = "";
    public $element_attributes;
    public $html = "";
    public $callback = "";
    public $cb_params = array();
    public $early_setup = false;
    public $controller = "";
    public $visibility = true;//can be typically overriden by a check on a user option

    public $defaults   = array();

    //on instantiation the id is unique and the priority propery setup
    //=> those treatments have been managed by the collection
    function __construct( $model = array() ) {
          self::$instance =& $this;
          $CZR            = CZR();

          //here is where extension classes can set their preset models
          $_preset = $this -> czr_fn_get_preset_model();

          if ( is_array($_preset) && ! empty( $_preset ) )
            $model = wp_parse_args( $_preset, $model );

          //here is where extension classes can modify the model params before they're parsed
          //becoming model properties
          $model = $this -> czr_fn_extend_params( $model );


          if ( empty( $model ) ) {
            do_action('czr_dev_notice', 'in CZR_MODEL construct : a model has no id ');
            return;
          } elseif ( FALSE == $model['id'] ) {
            //if model ID has been set to false => silent exit. Useful in cases when in czr_fn_extend_params the model
            //itself understands that it has to exit its instantiation
            $CZR -> collection -> czr_fn_delete( $this -> id );
            return;
          }


          //inside will make the equivalent of wp_parse_args() with default model property values
          $this -> czr_fn_update( $model );

          //at this stage the mode must at least have :
          //1) a unique id
          //2) a priority set
          //3) a hook => not anymore since czr_fn_render_template()
          if ( ! $this -> czr_fn_can_model_be_instantiated() ) {
            $CZR -> collection -> czr_fn_delete( $this -> id );
            return;
          }

          //maybe alter body class
          if ( method_exists( $this, 'czr_fn_body_class' ) )
            add_filter( 'body_class', array( $this, 'czr_fn_body_class' ) );

          //maybe add style
          $this -> czr_fn_maybe_add_style();

          //a way to allow models to act on their view
          $this -> czr_fn_add_view_pre_and_post_actions();

          //Allow models to filter their view visibility
          add_filter( "czr_do_render_view_{$this -> id}", array( $this, 'czr_fn_maybe_render_this_model_view' ), 0 );

          //adds the view instance to the model : DO WE REALLY NEED TO DO THAT ?
          //view instance as param
          add_action( "view_instantiated_{$this -> id}"   , array( $this, 'czr_fn_add_view_to_model'), 10, 1 );

          //takes the view instance as param
          add_action( "view_instantiated_{$this -> id}"   , array( $this, 'czr_fn_maybe_hook_or_render_view'), 20, 1 );


          //Maybe instantiate the model's view
          //listens to 'wp' if not fired yet, or fire the instantiation
          if ( ! did_action('wp') )
            add_action( 'wp'                                , array( $this, 'czr_fn_maybe_instantiate_view' ), 999 );
          else
            $this -> czr_fn_maybe_instantiate_view();

    }//construct


    //add this instance to the view description in the collection
    //=> can be used later for deregistration
    //hook : view_instantiated
    function czr_fn_add_view_to_model( $view_instance ) {
          $this -> czr_fn_set_property( 'view_instance', $view_instance );
    }



    /**********************************************************************************
    * INSTANCIATE THE MODEL VIEW => check the controllers,
    * @wp_timezone_override_offset(); check if it's been changed or deleted ?
    ***********************************************************************************/
    //default hook : wp | 1000
    //@return void()
    public function czr_fn_maybe_instantiate_view() {
          do_action( "pre_instantiate_view" );
          $CZR            = CZR();
          //this check has already been done before instantiating the model.
          //Do we really need this again here ?
          if ( ! $CZR -> controllers -> czr_fn_is_possible($this -> czr_fn_get_model_as_array() )  )
            return;

          //instantiate the view with the current model object as param
          $view_instance = new CZR_View( $this );
    }//fn



    /**********************************************************************************
    * ACTIONS ON MODEL INSTANCIATION : MAYBE ADD SPECIFIC MODEL STYLE
    ***********************************************************************************/
    public function czr_fn_maybe_add_style() {
          //for now just add filter to czr_fn_user_options_style
          if ( method_exists( $this, 'czr_fn_user_options_style_cb' ) )
            add_filter( 'czr_user_options_style', array( $this, 'czr_fn_user_options_style_cb' ) );
    }//fn



    /***********************************************************************************
    * ACTIONS ON VIEW READY
    * => THE POSSIBLE VIEW CLASS IS NOW INSTANCIATED
    ***********************************************************************************/
    //hook : 'view_instantiated'
    //@param $instance is the view instance object, can be CZR_View or a child of CZR_View
    //hook the rendering method to the hook
    //$this -> view_instance can be used. It can be a child of this class.
    public function czr_fn_maybe_hook_or_render_view( $instance )  {
          if ( empty( $this -> id ) ) {
              do_action('czr_dev_notice', 'In CZR_Model, a model is missing its id.' );
              return;
          }

          //Are we in czr_fn_render_template case
          //=> Typically yes if did_action('template_redirect'), since every model are registered on 'wp'
          //AND if the render property is forced to true
          //if not check if template_redirect has already been fired, to see if we are in a czr_fn_render case
          if ( did_action( 'template_redirect' ) && $this -> render ) {
              $instance -> czr_fn_maybe_render();
              return;//this is the end, beautiful friend.
          }

          //What are the requested hook and priority ?
          //=> this can be overriden in an extended model for example
          //? using the czr_fn_set_property ( 'hook' , 'value' ) could also be an option in an extended model ?
          $_da_hook     = apply_filters("_da_hook_{$this -> id}" , $this -> hook );
          $_da_priority = apply_filters("_da_priority_{$this -> id}" , $this -> priority );


          //Renders the view on the requested hook
          //'cause yes we do have a hook at this point, who doubts about that ?
          //Well me, but I know I shouldn't. I'm just a freaky damned scary lone coder in the night riding a sparkle horse.
          if ( false == $_da_hook )
            return;

          add_action( $_da_hook, array( $instance , 'czr_fn_maybe_render' ), $_da_priority );
          //emit an event each time a view is hooked
          do_action( 'view_hooked' , $this -> id );
    }


    //@param instance view
    //@return void()
    public function czr_fn_unhook_view() {
          if ( false == $this -> hook || ! is_object( $this -> view_instance) )
            return;
          remove_action( $this -> hook, array( $instance , 'czr_fn_maybe_render' ), $this -> priority );
          //say it
          do_action( 'view_unhooked' , $this -> id );
    }


    /***********************************************************************************
    * EXPOSED GETTERS / SETTERS
    ***********************************************************************************/
    //normalizes the way we can access and change a single model property
    //=> emit an event to update the collection
    //@return void()
    public function czr_fn_set_property( $property, $value ) {
          $this -> $property = $value;

          //will trigger a collection update
          //pass : id, (object) model, changed property, new property value
          do_action( 'model_property_changed', $this -> id, $this , $property, $value );
    }

    //normalizes the way we can access and change a single model property
    //@return the property
    public function czr_fn_get_property( $property, $args = array() ) {
          if ( method_exists( $this, "czr_fn_get_{$property}" ) ) {
              return call_user_func_array( array($this, "czr_fn_get_{$property}"), $args );
          }
          return isset ( $this -> $property ) ? $this -> $property : '';
    }

    //@returns the model property as an array of params
    public function czr_fn_get_model_as_array() {
          $model = array();
          foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
            $model[ $key ] = $this->$key;
          }
          return $model;
    }

    //@return array()
    //Extension models can use this to declare a preset model
    //is fired on instantiation
    //@return array()
    protected function czr_fn_get_preset_model() {
      return array();
    }

    //@return array()
    //Extension models can use this to update the model params with a set of new ones
    //is fired on instantiation
    //@param = array()
    protected function czr_fn_extend_params( $model = array() ) {
          //parse args into the model
          if ( ! empty( $model[ 'args' ] ) && is_array( $model[ 'args' ] ) ) {
            $model = wp_parse_args( $model[ 'args' ], $model );
            unset( $model[ 'args' ] );
          }
          return $model;
    }

    //@return void()
    //update the model properties with a set of new ones
    //is fired on instantiation
    //@param = array()
    public function czr_fn_update( $model = array(), $merge_defaults = true ) {
          /*
          * Parse model into $this->defaults, if not empty
          * This property will be merged with the array of properties
          * It allows us, when updating a model, to specify only those properties that need to be different from the defaults.
          * Specially interesting for those "singleton" models, whose only one instance is used throughout the page.
          * The fact that the models retain the properties could cause undesired effects.
          * E.g.
          * - Edit Button
          * the edit button model, which feeds the template,
          * is filled by the slider of posts with a text saying "Customize or remove the posts slider",
          * this means that if we render the edit button in a list of posts below the slider
          * we are forced to specify the new text, which, in most of the cases would be just "Edit" (defaults)
          */
          if ( $merge_defaults && ! empty( $this -> defaults ) ) {
                $model = wp_parse_args( $model, $this->defaults );
          }

          foreach ( $model as $key => $value ) {
               if ( ! isset( $this->key) || ( isset( $this->$key ) && $model[ $key ] != $this->$key ) )
                      $this->$key = $model[ $key ];
          }

          //emit an event when a model is updated
          do_action( 'model_updated', $this -> id );
    }

    /*
    * Reset the defaults properties to their original values
    */
    public function czr_fn_reset_to_defaults () {
          $this -> czr_fn_update( $this->defaults );
    }



    /***********************************************************************************
    * ACTIONS ON VIEW READY
    * => THE POSSIBLE VIEW CLASS IS NOW INSTANCIATED
    ***********************************************************************************/
    //@hook czr_do_render_view_{$this -> id}
    //@return bool
    // Controls the rendering of the model's view
    public function czr_fn_maybe_render_this_model_view() {
          return $this -> visibility;
    }

    // Maybe add pre_rendering_view action hook callback to filter the model before rendering
    // Extended classes might want to override this method, so to hook them to a specific pre rendering id
    // I prefer to not allow the automatic hooking to a specific view without checking the existence of a callback to avoid the useless adding of a "dummy" cb ot the array of action callbacks
    // Though makes sense to hook a certain model ID to its view pre_rendering to parse its own properties before rendering. Example:
    // The class parameter will be stored in the model as an array to allow a better way to filter it ( e.g. avoid duplications), but to make it suitable for the rendering, it must be transformed in a string
    // Maybe we can think about make the model attributes, when needed, a set of
    // value, "sanitize" callback and let the view class do this..
    protected function czr_fn_add_view_pre_and_post_actions() {

          //by default filter this module before rendering (for default properties parsing, e.g. element_class )
          add_action( "pre_rendering_view_{$this -> id}", array($this, "czr_fn_pre_rendering_my_view_cb" ), 9999 );

          //by default filter this module before rendering (for default properties parsing, e.g. element_class )
          add_action( "post_rendering_view_{$this -> id}", array($this, "czr_fn_post_rendering_my_view_cb" ), 9999 );
    }


    /*
    * Before rendering this model view allow the setup of the late properties (e.g. in the loops)
    * Always sanitize those properties for being printed ( e.g. array to string )
    */
    public function czr_fn_pre_rendering_my_view_cb( $model ) {
          if ( method_exists( $this, 'czr_fn_setup_late_properties' ) )
            $this -> czr_fn_setup_late_properties();

          $this -> czr_fn_sanitize_model_properties( $model );
    }

    /*
    * After rendering this model view allow the reset (e.g. in the loops)
    */
    public function czr_fn_post_rendering_my_view_cb( $model ) {
          if ( method_exists( $this, 'czr_fn_reset_late_properties' ) )
            $this -> czr_fn_reset_late_properties();

    }

    protected function czr_fn_sanitize_model_properties( $model ) {
          $this -> element_class  = $this -> czr_fn_stringify_model_property( 'element_class' );
    }


    /**********************************************************************************
    * HELPERS
    ***********************************************************************************/
    protected function czr_fn_stringify_model_property( $property ) {
          if ( isset( $this -> $property ) )
           return czr_fn_stringify_array( $this -> $property );
          return '';
    }


    //@return bool
    //at this stage the mode must at least have :
    //1) a unique id
    //2) a priority set
    private function czr_fn_can_model_be_instantiated() {
      //the model must be an array of params
      //the hook is the only mandatory param => not anymore since czr_fn_render_template()
      if ( ! is_numeric( $this -> priority ) || empty($this -> id) ) {
        do_action('czr_dev_notice', "In CZR_Model class, a model instantiation aborted. Model is not ready for the collection, it won't be registered. at this stage, the model must have an id, a hook and a numeric priority." );
        return;
      }
      return true;
    }

    //checks if the model exists and is an instance
    //@return bool
    public function czr_fn_has_instantiated_view() {
          return is_object( $this -> view_instance );
    }

  }//end of class
endif;

?><?php
//This is the class managing the collection of models. Here's what it does :
//- registers and de-registers models
//- ensures that each model has a unique id and is well formed
//- Instantiates the relevant models according to their controllers (for models registered on or after 'wp')
//- Instantiates the specified model extended class if any
//- Handles the model's modifications, including deletion
//- Make sure the collection is a public array of model's instance
if ( ! class_exists( 'CZR_Collection' ) ) :
  class CZR_Collection {
    static $instance;
    //public $group = "";//header,content,footer,modules
    //private $args = array();//will store the updated args on model creation and use them to instantiate the child
    public static $pre_registered = array();//will store the models before they are actually checked, instantiated and registered => before 'wp'
    public static $collection = array();//will store all registered model instances
    public static $_delete_candidates = array();//will store deletion of models not added yet

    function __construct( $args = array() ) {
      self::$instance =& $this;
      //listens to filter 'czr_prepare_model', takes 1 param : raw model array()
      //makes sure the model has a unique $id set and a proper priority for its rendereing hook
      //model as param
      add_filter( 'czr_prepare_model'            , array( $this, 'czr_fn_set_model_base_properties'), 10, 1 );

      //if 'wp' has not been fired yet, we will pre-register this model for later instantiation
      //2 params :
      //1) model id
      //2) model instance
      add_action ('pre_register_model'          , array( $this, 'czr_fn_pre_register_model'), 10, 2 );

      //a model_alive event is emitted each time a model object has been properly instantiated and setup
      //=> update the collection by registering the model
      //Takes 2 params
      //1) model id
      //2) model instance
      add_action( 'model_alive'                 , array( $this, 'czr_fn_update_collection' ), 10, 2 );

      //on 'wp', the pre_registered (if any) are registered
      add_action( 'wp'                          , array($this, 'czr_fn_register_pre_registered') );

      //Reacts on 'czr_delete' event
      //1 param = model id
      add_action( 'czr_delete'                   , array( $this, 'czr_fn_delete' ), 10, 1 );

      //listens to a model changed => update the model collection
      //model_property_changed takes two params :
      //model id
      //model params
      add_action( 'model_property_changed'      , array( $this, 'czr_fn_update_collection' ), 10, 2 );

      //listens to a registered change applied to a model => remove it from the register changes list
      //takes one param : model id
      add_action( 'registered_changed_applied'  , array( $this, 'czr_fn_deregister_change' ), 10, 1 );

      //reacts when a model has been deregistered from the collection
      //=> fire czr_fn_delete()
      //=> take the model id as param
      add_action( 'model_deregistered'          , array( $this , 'czr_fn_delete'), 10, 1 );
    }




    /**********************************************************************************
    * REGISTER A MODEL TO THE COLLECTION
    ***********************************************************************************/

    public function czr_fn_register( $model = array() ) {
      $_model_params_array = $model;

      //the first check on the raw model provided
      //=> if the model has been pre-registered and has an id, we also have to check here if it's registered for deletion
      if ( ! $this -> czr_fn_is_model_eligible( $model ) )
        return;

      //this pre-setup will ensure :
      //- the hook is there
      //- the id unicity
      //- the hook priority setup
      //It also makes sure that the registered changes will be applied
      $model = apply_filters( 'czr_prepare_model' , $model );

      //make sure the provided model has at least a hook property set
      //the model must be an array of params
      //the hook is the only mandatory param
      //the id is optional => will be set unique on model setup
      //if 'wp' has not been fired yet, we will pre-register this model for later registration
      ////Once the model is eligible and properly prepared (unique id), let's see if we can
      //1) register it,
      //2) pre-register it,
      //3) or simply abort registration
      if ( ! $this -> czr_fn_can_register_model( $model ) )
        return;

      //INSTANTIATE THE MODEL
      // => the model object will instantiate the view
      // => if already  did_action( 'template_redirect' ) and render == true, the view will be rendered
      //Instantiates the model object : at this stage the 'wp' hook has been fired and we're ready to instantiate the (maybe pre-registered) model
      //at this stage, the model is an array and :
      //- has an id
      //- has a priority
      //- is at least assigned to a hook
      //- we've checked if it was registered for deletion
      //=> let's instantiate
      $_model_id = isset( $model['id'] ) ? $model['id'] : 'undefined';

      $model = $this -> czr_fn_instantiate_model( $model );

      //Silent aborting for those models which "decided" in their constructor they're not allowed to be registered
      if ( isset( $model -> id ) && $this -> czr_fn_has_registered_deletion( $model -> id ) )
        return;

      //abort if the model has not been instantiated
      if ( ! is_object( $model ) ) {
        $_model_id = isset( $model -> id ) ? $model -> id : 'undefined';
        do_action('czr_dev_notice', "The model ( " . $_model_id . ") was not instantiated and could not be registered into the collection." );
        return;
      }

      //REGISTER THE MODEL INTO THE COLLECTION
      //At this stage, the model is an instanciated object
      //=> an event is emitted : this will trigger the collection update => the model will be registered in the collection
      do_action( 'model_alive' , $_model_id, $model );

      //specific event for this model.
      do_action( "{$_model_id}_model_alive", $_model_id, $model );


      if ( $this -> czr_fn_is_registered( $model -> id ) ) {
        //emit an event on model registered
        //can be used with did_action() afterwards
        do_action( "model_registered_{$model -> id}" );
      } elseif ( ! empty( $model -> id ) ) {
        do_action('czr_dev_notice', "A model instance ( " . $model -> id . ") was not registered into the collection." );
        return;
      } else //silent exit ( for those models whose instatiation has been stopped in the constructor as result of the model business logic )
        return;
      return $model -> id;
    }







    /**********************************************************************************
    * BEFORE REGISTRATION
    ***********************************************************************************/
    //hook : 'czr_can_use_model'
    //Check if the model is registered for deletion first
    //the model must be an array of params
    //the hook is the only mandatory param => not anymore since czr_fn_render_template
    //the id is optional => will be set unique on model instantiation
    public function czr_fn_is_model_eligible( $model = array() ) {
      //is model registered for deletion ?
      if ( isset( $model['id'] ) && $this -> czr_fn_has_registered_deletion( $model['id'] ) )
        return;

      if ( ! is_array($model) || empty($model) ) {
        do_action('czr_dev_notice', "CZR_collection : A model is not eligible for the collection, it won't be registered. The model must be an array of params." );
        return;
      }
      return true;
    }



    //at this stage, the model has a hook but the id unicity, and the priority have not been checked yet
    //=> we need to make sure the model has a unique $id and a proper priority for its rendering hook
    //hook filter 'czr_prepare_model' in czr_fn_register
    //@param model array
    //@return model array() updated
    public function czr_fn_set_model_base_properties( $model = array() ) {
      $id       = isset($model['id']) ? $model['id'] : "";
      $priority = isset($model['priority']) ? $model['priority'] : "";
      $template = isset($model['template']) ? $model['template']  : "";//the template name can be used to define the id

      if ( isset($model['hook']) && ! empty($model['hook']) && false != $model['hook'] ) {
        //makes sure we assign a unique ascending priority if not set
        $model['priority']  = $this -> czr_fn_set_priority( $model['hook'] , $priority );
      } else {
        $model['priority'] = 10;
        $model['hook'] = "";
      }
      //check or set the name unicity
      $model['id']        = $this -> czr_fn_set_unique_id( $id , $model['hook'], $model['priority'], $template );

      //don't go further if we still have no id set
      if ( ! $model['id'] ) {
        do_action('czr_dev_notice', "A model has no id set." );
        return;
      }

      //at this stage the priority is set and the id is unique
      //a model with a unique id can be registered only once
      //a model with a promise registered deletion won't be registered
      if ( $this -> czr_fn_is_registered( $model['id'] ) ) {
        do_action('czr_dev_notice', "CZR_Collection. Model : ". $model['id'] ." . The id is still not unique. Not registered." );
        return;
      }
      return $model;
    }


    //at this point, the raw model has had a first setup to ensure id
    //@return boolean
    //@param array() raw model
    private function czr_fn_can_register_model( $model = array() ) {
      $bool = false;
      $CZR  = CZR();
      //the first check is on the visibility
      //Typically : Has the user allowed this model's view in options ?
      if ( isset( $model['visibility']) && ! (bool) $model['visibility'] )
        $bool = false;

      //if the model has early hooks (before wp) , typically a pre_get_post action for example
      // => the the model has to be instantiated
      if ( isset($model['early_setup']) && ! empty($model['early_setup']) )
        $bool = true;

      //if 'wp' has not been fired yet, we will pre-register this model for later registration
      if ( ! did_action('wp') ) {
        //we will use this event to fire the pre-registration
        do_action( 'pre_register_model', $model['id'], $model );
        $bool = false;
      }
      //if 'wp' has been fired (or is currently being fired) 1) check the controller if set
      else {
        $bool = $CZR -> controllers -> czr_fn_is_possible( $model );
      }
      return apply_filters('czr_can_register_model', $bool, $model );
    }



    /**********************************************************************************
    * PRE-REGISTRATION
    ***********************************************************************************/
    //update the pre_register static property
    //hook : pre_register_model
    //@return void()
    //@param id string
    //@param model array
    function czr_fn_pre_register_model( $id, $model = array() ) {
      $pre_registered = self::$pre_registered;
      //is this model already pre_registered ?
      //=> if yes, it can't be registered again. However it should be accessible with a change action.
      if ( isset($pre_registered[$id]) ) {
        do_action('czr_dev_notice', "Model " . $id . " has already been pre-registered." );
        return;
      }

      $pre_registered[$id] = $model;
      self::$pre_registered = $pre_registered;
    }


    //@return void()
    //=> removes a pre_register model from the pre_registered list
    function czr_fn_remove_pre_registered($id) {
      $pre_registered = self::$pre_registered;
      if ( isset($pre_registered[$id]) )
        unset($pre_registered[$id]);
      self::$pre_registered = $pre_registered;
    }


    //registers the pre-registered model when query is ready
    //hook : wp
    //@return void()
    function czr_fn_register_pre_registered() {
      foreach ( self::$pre_registered as $id => $model ) {
        //removes from the pre_registered list
        $this -> czr_fn_remove_pre_registered($id);
        //registers
        $this -> czr_fn_register($model);
      }
      //say it to the api
      do_action( 'pre_registered_registered', self::$pre_registered );
    }





    /**********************************************************************************
    * INSTANCIATE THE MODEL'S CLASS
    ***********************************************************************************/
    //some model's need to be augmented by an extended class.
    //hook : 'wp'
    //this method load the relevant model class file and return the instance
    //@return instance object
    public function czr_fn_instantiate_model($model) {
      $instance = null;

      //try to instantiate the model specified in the model_class param
      //if not found try to retrieve it from the template param (mandatory):
      //a) The model_class, when specified, must refer to a valid model otherwise a notice will be fired.
      //b) Also if a whatever model has been instantiated it must be a subclass of CZR_Model - otherwise a notice will be fired.
      //c) Else If no suitable model has been instantiated instantiate the base model class
      foreach ( array( 'model_class', 'template' ) as $_model_class ) {
        if ( ! isset($model[ $_model_class ]) || empty($model[ $_model_class ]) )
            continue;

        //A model class has been defined, let's try to load it and instantiate it
        //The model_class arg can also be an array in the form array( 'parent' => parent_model_class (string), 'name' => model_class ('string') )
        if ( 'model_class' == $_model_class && is_array( $model[ 'model_class' ] ) && array_key_exists( 'name', $model['model_class'] ) ) {

          if ( ! class_exists( sprintf( 'CZR_%s_model_class', $model['model_class']['parent'] ) ) ) {
            $this -> czr_fn_require_model_class( $model['model_class']['parent'] );
          }

          $model_class     = $model[ $_model_class ]['name'];

        } else {
          $model_class     = $model[ $_model_class ];
        }


        $model_class_name     = sprintf( 'CZR_%s_model_class', basename( $model_class ) );

        if ( ! class_exists($model_class_name) ) {
          $this -> czr_fn_require_model_class( $model_class );
        }


        if ( class_exists($model_class_name) ) {
          $instance = new $model_class_name( $model );
        }

        if ( ! is_object($instance) && 'model_class' == $_model_class ) {
          do_action('czr_dev_notice', "Model : " . $model['id'] . ". The model has not been instantiated." );
          return;
        }
        //A model must be CZR_model or a child class of CZR_model.
        if ( is_object($instance) && ! is_subclass_of($instance, 'CZR_Model') ) {
          do_action('czr_dev_notice', "Model : " . $model['id'] . ". View Instantiation aborted : the specified model class must be a child of CZR_Model." );
          return;
        } else break;
      }//end foreach

      if ( ! is_object( $instance ) )
        return new CZR_Model( $model );

      return $instance;
    }


    //This method require the requested class file and returns a boolean state of this action
    //the boolean is required. If false, the theme will fallback on the CZR_Model base class
    private function czr_fn_require_model_class( $_model_class ) {
        $model_class_basename = basename( $_model_class );
        $model_class_dirname  = dirname( $_model_class );
        $model_path           = sprintf( 'models/%1$s/class-model-%2$s.php', $model_class_dirname, $model_class_basename );

        //this filter is NOT used in the customizr theme
        //=> its intended to be used in plugins / addons, like pro grid for ex.
        $path = apply_filters(
            "czr_model_class_path",
            false,
            $model_class_basename,
            $model_path
        );

        //If not filtered ( normal case in customizr ), use the default path prefix + model path
        if ( ! $path || ! file_exists( $path ) ) {
          return  CZR() -> czr_fn_require_once(
              CZR_PHP_FRONT_PATH . $model_path
          );
        }

        return require_once( $path );
    }








    /**********************************************************************************
    * UPDATE COLLECION
    ***********************************************************************************/
    //hook : 'model_alive' and 'model_property_changed'
    //The job of this method is :
    //1) to add a model to the collection
    //2) or to update an existing model
    //
    //=> always update the model list before rendering something
    //=> a model might have been registered in the delete / change candidates
    //=> this is fired on model_property_changed event
    //=> when a single model property has been changed in CZR_Model::czr_fn_set_property()
    //@param id string
    //@param $model instance object
    public function czr_fn_update_collection( $id, $model ) {
      if ( ! $id || ! is_object($model) )
        return;

      //Check if we have to run a registered deletion here
      if ( $this -> czr_fn_is_registered( $id ) && $this -> czr_fn_has_registered_deletion( $id ) ) {
        do_action( 'czr_delete' , $id );
        return;
      }

      //Adds or updates a specific model in the collection
      $collection = self::$collection;
      $collection[$id] = $model;
      self::$collection = $collection;

      //emit an event on each collection updates
      do_action( 'collection_updated', $id, $model );
    }




    /**********************************************************************************
    * DE-REGISTER AN INSTANCIATED MODEL
    ***********************************************************************************/
    //keep in mind that the instance of the previous model with initial argument will still exists...
    //so will the additional class instance if any
    //@todo shall we store all models instances and delete them when requested ?
    public function czr_fn_deregister( $id, $model ) {
      if ( ! is_array($model) )
        return;

      //Removes the previously set action
      if ( ! is_object($model['view_instance']) )
        do_action('czr_dev_notice', 'Attempt to de-register, but no model instance for id : '. $id );
      else if ( ! empty( $model['hook'] ) )
        remove_action( $model['hook'], array( $model['view_instance'], 'czr_fn_maybe_render'), $model['priority'] );

      //Emit an event on model deregistered
      //=> will trigger the model delete action from collection
      do_action( 'model_deregistered' , $id );
    }





    /**********************************************************************************
    * DELETE A MODEL FROM THE COLLECTION
    ***********************************************************************************/
    //the model might not have been created yet
    //=> register a promise deletion in this case
    //IMPORTANT : always use the CZR_Collection::$instance -> _models property to access the model list here
    //=> because it can be accessed from a child class
    public function czr_fn_delete( $id = null ) {
      if ( is_null($id) )
        return;

      $collection = self::$collection;
      if ( isset($collection[$id]) ) {
        unset($collection[$id]);
        self::$collection = $collection;
        //may be remove from the deletion list
        $this -> czr_fn_deregister_deletion($id);
        //Emit an event on model deleted
        do_action( 'model_deleted' , $id );
      }
      else
        $this -> czr_fn_register_deletion( $id );
      return;
    }


    private function czr_fn_deregister_deletion($id) {
      $to_delete = self::$_delete_candidates;
      if ( $this -> czr_fn_has_registered_deletion($id) )
        unset($to_delete[$id]);
      self::$_delete_candidates = $to_delete;
    }


    private function czr_fn_register_deletion($id) {
      $to_delete = self::$_delete_candidates;
      //avoid if already registered for deletion
      if ( $this -> czr_fn_has_registered_deletion($id) )
        return;

      $to_delete[$id] = $id;
      self::$_delete_candidates =  $to_delete;
    }


    private function czr_fn_has_registered_deletion($id) {
      return array_key_exists( $id, self::$_delete_candidates );
    }




    /**********************************************************************************
    * GETTERS / SETTERS
    ***********************************************************************************/
    //@return a single model set of params array
    public function czr_fn_get_model( $id = null ) {
      $collection = self::$collection;
      if ( ! is_null($id) && isset($collection[$id]) )
        return (array)$collection[$id];
      return array();
    }

    //@return model instance or false
    //@param model id string
    public function czr_fn_get_model_instance( $id = null ) {
      if ( is_null($id) )
        return;

      $collection = self::$collection;
      if ( ! isset($collection[$id]) )
        return;
      return $collection[$id];
    }


    //@return the collection of models
    public function czr_fn_get_collection() {
      //uses self::$instance instead of this to always use the parent instance
      return self::$collection;
    }


    /**********************************************************************************
    * HELPERS
    ***********************************************************************************/
    //@return bool
    private function czr_fn_is_pre_registered($id) {
      return array_key_exists($id, self::$pre_registered);
    }


    //this function recursively :
    //1) checks if the requested priority is available on the specified hook
    //2) set a new priority until until it's available
    private function czr_fn_set_priority( $hook, $priority ) {
      $priority = empty($priority) ? 10 : (int)$priority;
      $available = true;
      //loop on the existing model object in the collection
      foreach ( $this -> czr_fn_get_collection() as $id => $model) {
        if ( ! isset($model -> hook) )
          continue;
        if ( $hook != $model -> hook )
          continue;
        if ( $hook == $model -> hook && $priority != $model -> priority )
          continue;
        $available = false;
      }
      return $available ? $priority : $this -> czr_fn_set_priority( $hook, $priority + 1 );
    }


    //Recursively create a unique id when needed
    //@return string id
    private function czr_fn_set_unique_id( $id, $hook, $priority, $template ) {
      //if id not set, then :
      //1) try to create a unique id from the template name if specified
      //2) otherwise create a unique id from hook_priority
      if ( empty($id) || is_null($id) ) {
        if ( ! empty($template) )
          $id = basename( $template );
        else
          $id = "{$hook}_{$priority}";
      }


      //return it now if available
      if ( ! $this -> czr_fn_is_registered($id) && ! $this -> czr_fn_is_pre_registered($id) )
        return apply_filters('czr_set_model_unique_id' , $id, $hook, $priority );

      //add hyphen add the end if not there
      $id                 = ! is_numeric(substr($id, -1)) ? $id . '_0' : $id;
      $id_exploded        = explode('_' , $id);
      $_index             = end($id_exploded);
      $_key               = key($id_exploded);
      //set new value
      $id_exploded[$_key] = $_index + 1;
      $id                 = implode( "_" , $id_exploded );

      //recursive check
      return $this -> czr_fn_set_unique_id( $id, $hook, $priority, $template );
    }



    //checks if a model exists in the collection
    //@param string id
    //@return bool
    public function czr_fn_is_registered( $id ) {
      return array_key_exists( $id, self::$collection );
    }

  }//end of class
endif;

?><?php
//This is the view class. Each front end part of the Customizr theme is rendered through a view instance.
//Each view inherits its properties from a model instance.
//The model decides if it has to instantiate the view or not, according to the context

//Once properly instantiated with the model as parameter, a view does not think to anything else than rendering what and where (hook) we ask it to.
//This class jobs are :
//- render either html, WordPress template or more complex content, depending on the its model settings.
//- when renders on a template, the view must pass its model to the WordPress template through the $wp_query global


if ( ! class_exists( 'CZR_View' ) ) :
  class CZR_View {
    public $model;
    function __construct( $model ) {
          // $keys = array_keys( get_object_vars( $this ) );

          // foreach ( $keys as $key ) {
          //   if ( isset( $model[ $key ] ) ) {
          //     $this->$key = $model[ $key ];
          //   }
          // }
          $this -> model = $model;

          //emit event on view instantiation
          //Will be listen to by the model and trigger the maybe_hook_view callback
          do_action( "view_instantiated_{$this -> model -> id}", $this );
    }




    /**********************************************************************************
    * RENDERS
    ***********************************************************************************/
    //hook : $model['hook']
    //NOTE : the $this here can be the child class $this.
    //
    //This method can fired or scheduled :
    //1) in function czr_fn_render_template( $template, $args = array() ) for an already registered model
    //  => Example : header
    //2) in CZR_Model::czr_fn_maybe_hook_or_render_view, FIRED if did_action('template_redirect') and the 'render' model property is true
    //3) else in CZR_Model::czr_fn_maybe_hook_or_render_view, SCHEDULED ( hooked )
    public function czr_fn_maybe_render() {
        //this event is used to check for late deletion or change before actually rendering
        //will fire czr_fn_apply_registered_changes_to_instance
        //do_action( 'pre_render_view', $this -> id );

        if ( ! apply_filters( "czr_do_render_view_{$this -> model -> id}", true, $this->model ) )
          return;

        //allow filtering of the model before rendering (the view's model is passed by reference)
        //THIS IS WHERE THE czr_fn_setup_late_properties is fired
        // With this action in the base model class constructor : add_action( "pre_rendering_view_{$this -> id}", array($this, "czr_fn_pre_rendering_my_view_cb" ), 9999 );
        //WHAT DOES SETUP LATE PROPERTIES?
        // => example for the loop model, it setup the template to render inside the loop, which are defined as model properties.
        //  public $loop_item_template
        do_action_ref_array( 'pre_rendering_view', array(&$this -> model) );
        do_action_ref_array( "pre_rendering_view_{$this -> model -> id}", array(&$this -> model) );

        //re-check visibility
        if ( ! apply_filters( "czr_do_render_view_{$this -> model -> id}", true, $this->model ) )
          return;

        //do_action( "__before_{$this -> model -> id}" ); <= DO WE REALLY NEED THOSE ?

        //ADD ATTRIBUTES TO THE WRAPPER
        if ( is_user_logged_in() ) {
            /* Maybe merge debug info into the model element attributes */
            if($this -> model -> element_attributes !== null){
              $this -> model -> element_attributes = is_array( $this -> model -> element_attributes ) ? $this -> model -> element_attributes : explode( ' ', $this -> model -> element_attributes );
              $this -> model -> element_attributes = join( ' ', array_filter( array_unique( array_merge( $this -> model -> element_attributes, array(
                  'data-czr-model_id="'. $this -> model -> id .'"',
                  isset( $this -> model -> template ) ? 'data-czr-template="templates/parts/'. $this -> model -> template .'"' : ''
              )))) );
            }
        }

        $this -> czr_fn_render();


        // (the view's model is passed by reference)
        do_action_ref_array( 'post_rendering_view', array(&$this -> model) );
        do_action_ref_array( "post_rendering_view_{$this -> model -> id}", array(&$this -> model) );
    }



    //might be overriden in the child view if any
    public function czr_fn_render() {
        if ( ! empty( $this -> model -> html ) )
            echo $this -> model -> html;
        //NOTE : a model -> template can be 'my_template' or 'content/post-lists/my_template'
        //=> if we want to use the template property as a suffix for filtering, we need to sanitize it
        if ( ! empty( $this -> model -> template ) ) {
            $template_path = trailingslashit( apply_filters( 'czr_template_path', "templates/parts/", $this -> model -> id, $this -> model ) );
            $_template_file_path = apply_filters( 'czr_template_file_path', false, $this -> model -> template );

            if ( false === $_template_file_path || ! file_exists( $_template_file_path ) ) {
                //get the filename
                $_template_file_path = czr_fn_get_theme_file_path( "{$template_path}{$this -> model -> template}.php" );
            }

            if ( false !== $_template_file_path ) {
                czr_fn_set_current_model( $this -> model );

                ob_start();
                  load_template( $_template_file_path, $require_once = false );
                  $_temp_content = ob_get_contents();
                ob_end_clean();
                if ( ! empty( $_temp_content ) )
                  echo $_temp_content;

                czr_fn_reset_current_model();
            }
        }

        if ( ! empty( $this -> model -> callback ) )
            czr_fn_fire_cb( $this -> model -> callback, $this -> model -> cb_params );
    }



    //at this stage, the view is instantiated
    //@return void()
    private function czr_fn_update_model_instance( $id, $new_params ) {
          //get current params
          $current_params = $this -> czr_fn_get_view($id);
          if ( ! $current_params || ! is_array($current_params) )
            return;
          //pre-process new params
          $new_params = wp_parse_args( $new_params, $current_params );

          //update the modified view properties
          //=> will automatically trigger the collection update
          foreach ($new_params as $property => $value) {
            if ( $value != $current_params[$property] )
              $current_params['view_instance'] -> czr_fn_set_property( $property, $value );
          }
    }


    /**********************************************************************************
    * PUBLIC HELPERS
    ***********************************************************************************/
    public function czr_fn_get_instance() {
          return $this;
    }

  }//class
endif;

?><?php
//the controllers are organized by groups in 4 classes
//Header, Content, Footer, Modules.
//The controller group classes are instantiated on demand if any of a view (or its child) requested it
//If the model is used as a method parameter, it shall be an array()
//=> because a controller can be checked and fired before the model has been instantiated
//=> always check the existence of the id
if ( ! class_exists( 'CZR_controllers' ) ) :
  class CZR_controllers {
    static $instance;
    private $controllers;
    static $controllers_instances = array();

    function __construct( $args = array() ) {
          self::$instance =& $this;
          $this -> controllers = array(
            'header' => array(

              'head',

              'topbar_wrapper',

              'title_alone',
              'title_next_logo',
              'logo_wrapper',
              'logo',

              'topbar_tagline',
              'branding_tagline_aside',
              'branding_tagline_below',
              'branding_tagline',
              'mobile_tagline',


              'navbar_primary_menu',
              'navbar_secondary_menu',
              'sidenav',
              'topbar_menu',
              'mobile_menu',

              'menu_button',
              'mobile_menu_button',

              'sidenav_menu_button',
              'sidenav_navbar_menu_button',

              'topbar_social_block',

              'desktop_topbar_search',
              'desktop_primary_search',
              'mobile_navbar_search',
              'mobile_menu_search',

              'desktop_topbar_wc_cart',
              'desktop_primary_wc_cart',
              'mobile_wc_cart',

              'primary_nav_utils',
              'topbar_nav_utils'
            ),

            'content' => array(
              'post_list',
              'post_list_masonry',
              'post_list_plain',
              'post_list_plain_excerpt',
              'right_sidebar',
              'left_sidebar',
              'single_author_info',
              'comment_list',
              'comments',
              'post',
              'posts_navigation',

              'regular_page_heading',
              'regular_post_heading',
              'regular_attachment_image_heading',

              'attachment_image',

              'archive_heading',
              'author_description',
              'posts_list_description',
              'search_heading',
              'post_heading',
              'lefts_social_block',
              'rights_social_block',
              'post_metas'
            ),
            'footer' => array(
              'btt_arrow',
              'footer_push',
              'footer_horizontal_widgets',
              'footer_widgets',
              'colophon',
              'footer_social_block'
            ),
            'modules' => array(
              'breadcrumb',
              'social_block',
              'post_list_grid',
              'main_slider',
              'main_posts_slider',
              'featured_pages',
              'search_full_page',
              'social_share',
              'author_socials',
              'related_posts'
            ),
          );

          //Store a group controller instance for later uses
          //takes 2 params
          //group name (string)
          //group controller instance (object)
          add_action( 'group_controller_instantiated', array( $this, 'czr_fn_store_controller_instance'),10, 2);
    }//__construct()




    /***********************************************************************************
    * EXPOSED API
    ***********************************************************************************/
    //1) checks if a controller has been specified for the view. It can be either a function, or the method of a class
    //
    //2) if nothing is specified for the view, then checks if the view controller belongs to a particular group
    //if a match is found, then the group controller class is instantiated if not yet
    //then the existence of the controller method is checked and fired if exists
    //
    //3) if no match is found, the view is not allowed
    //@ $model can be
    //  1) a model object,
    //  2) a model array (not instanciated),
    //  3) a string id of the model
    public function czr_fn_is_possible( $model ) {
          //if the specified param is an id, then get the model from the collection
          if ( is_string($model) )
            $model = array('id' => $model );

          //abort with true if still no model at this stage.
          if ( empty($model) )
            return true;

          //the returned value can be a string or an array( instance, method)
          $controller_cb = $this -> czr_fn_get_controller( $model );
          //FOR TEST ONLY
          //return true;

          if ( ! empty( $controller_cb ) ) {
            return apply_filters( 'czr_fn_set_control' , (bool) czr_fn_return_cb_result( $controller_cb, $model ) );
          }
          return true;
    }



    //@return bool
    //@param array() or object() model
    public function czr_fn_has_controller( $model = array() ) {
          $controller = $this -> czr_fn_build_controller( $model );
          return ! empty( $controller );
    }



    //@return a function string or an array( instance, method )
    //@param array() or object() model
    private function czr_fn_get_controller( $model = array() ) {
          $model = is_object($model) ? (array)$model : $model;
          $controller_cb = "";

          //IS A CONTROLLER SPECIFIED AS PROPERTY OF THIS MODEL ?
          //and is a valid callback
          if ( isset($model['controller']) && is_callable( $model['controller'] )) {
            //a callback can be function or a method of a class
            $controller_cb =  $model['controller'];
          }
          //IS THERE A PRE-DEFINED CONTROLLER FOR THE VIEW ?
          else {
            //the default controller match can either be:
            //a) based on the model id (has the precedence )
            //b) based on the controller model field, when not a callback
            $controller_ids   = array_filter( array(
                $model['id'],
                ! empty( $model['controller'] ) ? $model['controller'] : '',
              )
            );
            if ( $this -> czr_fn_has_default_controller( $controller_ids ) ) {
              $controller_cb = $this -> czr_fn_get_default_controller( $controller_ids );
              //make sure the default controller is well formed
              //the default controller should look like array( instance, method )
              if ( empty($controller_cb) ) {
                do_action( 'czr_dev_notice', 'View : '.$model['id'].'. The default group controller has not been instantiated');
                return "";
              }//if
            }//if has default controller
          }//else
          return $controller_cb;
    }



    //@return boolean
    //@walks the controllers setup array until a match is found
    private function czr_fn_has_default_controller( $controller_ids ) {
          foreach ( $this -> controllers as $group => $views_id ) {
            foreach( $controller_ids as $id ) {
              if ( in_array($id, $views_id) ) {
                return true;
              }
            }//foreach
          }//foreach
          return false;
    }


    //tries to find a default controller group for this method
    //@return array(instance, method) or array() if nothind found
    private function czr_fn_get_default_controller( $controller_ids ) {
          $controller_cb = false;
          foreach ( $this -> controllers as $group => $views_id ) {
            foreach( $controller_ids as $id ) {
              if ( in_array($id, $views_id) ) {
                $controller_cb = $id;
                $controller_group = $group;
                break 2;
              }//if
            }//foreach
          }//foreach
          //return here is no match found
          if ( ! $controller_cb ) {
            do_action( 'czr_dev_notice', 'View : '.$id.'. No control method found.');
            return array();
          }


          //Is this group controller instantiated ?
          if ( ! array_key_exists($controller_group, self::$controllers_instances) ) {
            //this will actually load the class file and instantiate it
            $_instance = $this -> czr_fn_instantiate_group_controller($controller_group);
          } else {
            $_instance = $this -> czr_fn_get_controller_instance($controller_group);
          }

          //stop here if still nothing is instantiated
          if ( ! isset( $_instance) || ! is_object( $_instance ) ) {
            do_action( 'czr_dev_notice', 'View : '.$id.'. The control class for : ' . $controller_group . ' has not been instantiated.' );
            return array();
          }

          //build the method name
          $method_name = "czr_fn_display_view_{$controller_cb}";//ex : czr_fn_display_view_{favicon_control}()


          //make sure we have a class instance and that the requested controller method exists in it
          if ( method_exists( $_instance , $method_name ) )
            return array( $_instance, $method_name );

          do_action( 'czr_dev_notice', 'model : '.$id.'. The method : ' . $method_name . ' has not been found in group controller : '.$controller_group );
          return array();
    }


    //load the class file if exists
    //instantiate the class is exists
    //@param is a string : header, content, footer, modules
    //@return the $instance
    private function czr_fn_instantiate_group_controller( $group ) {
          $_path     = "controllers/class-controller-{$group}.php";
          $_class    = "CZR_controller_{$group}";
          $_instance = false;
          $CZR       = CZR();

          $CZR -> czr_fn_require_once( CZR_PHP_FRONT_PATH . $_path );

          if ( class_exists($_class) ) {
            $_instance = new $_class;
            do_action( 'group_controller_instantiated', $group, $_instance );
          }
          return $_instance;
    }


    //listens to group_controller_instantiated
    //stores the groupd controller instance in the property : self::$controllers_instances
    //@return void()
    public function czr_fn_store_controller_instance( $group , $_instance ) {
          $controller_instances = self::$controllers_instances;
          if ( array_key_exists($group, $controller_instances) )
            return;

          $controller_instances[$group] = $_instance;
          self::$controllers_instances = $controller_instances;
    }


    //get an already instantiated controller group instance
    public function czr_fn_get_controller_instance( $group ) {
          $controller_instances = self::$controllers_instances;
          if ( ! array_key_exists($group, $controller_instances) )
            return;
          return $controller_instances[$group];
    }
  }//end of class
endif;

?>