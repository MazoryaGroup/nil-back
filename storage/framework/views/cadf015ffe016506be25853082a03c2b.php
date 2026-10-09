<?php extract(collect($attributes->getAttributes())->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['hasInlineLabel','id','label','labelSrOnly','helperText','hint','hintActions','hintColor','hintIcon','hintIconTooltip','statePath']) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['hasInlineLabel','id','label','labelSrOnly','helperText','hint','hintActions','hintColor','hintIcon','hintIconTooltip','statePath']); ?>
<?php foreach (array_filter((['hasInlineLabel','id','label','labelSrOnly','helperText','hint','hintActions','hintColor','hintIcon','hintIconTooltip','statePath']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>
<?php if (isset($component)) { $__componentOriginala86dcd7e3fb4428c61bb5e13aa161d28 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala86dcd7e3fb4428c61bb5e13aa161d28 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament-forms::components.field-wrapper.index','data' => ['hasInlineLabel' => $hasInlineLabel,'id' => $id,'label' => $label,'labelSrOnly' => $labelSrOnly,'helperText' => $helperText,'hint' => $hint,'hintActions' => $hintActions,'hintColor' => $hintColor,'hintIcon' => $hintIcon,'hintIconTooltip' => $hintIconTooltip,'statePath' => $statePath]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('filament-forms::field-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['has-inline-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hasInlineLabel),'id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($id),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'label-sr-only' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($labelSrOnly),'helper-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($helperText),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hint),'hint-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hintActions),'hint-color' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hintColor),'hint-icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hintIcon),'hint-icon-tooltip' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hintIconTooltip),'state-path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statePath)]); ?>

<?php echo e($slot ?? ""); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala86dcd7e3fb4428c61bb5e13aa161d28)): ?>
<?php $attributes = $__attributesOriginala86dcd7e3fb4428c61bb5e13aa161d28; ?>
<?php unset($__attributesOriginala86dcd7e3fb4428c61bb5e13aa161d28); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala86dcd7e3fb4428c61bb5e13aa161d28)): ?>
<?php $component = $__componentOriginala86dcd7e3fb4428c61bb5e13aa161d28; ?>
<?php unset($__componentOriginala86dcd7e3fb4428c61bb5e13aa161d28); ?>
<?php endif; ?><?php /**PATH D:\xampp\htdocs\nil-back\storage\framework\views/aaf6ca2447f1e5a9cb33b6183813a6d7.blade.php ENDPATH**/ ?>