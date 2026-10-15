<?php

namespace App\Models;
use App\Traits\StaticBoot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property array<array-key, mixed> $name
 * @property string $sub_of
 * @property string $icon
 * @property string|null $route
 * @property int $order
 * @property int $trash
 * @property string $section_side
 * @property int|null $updated_by
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches
 * @property-read int|null $branches_count
 * @property-read bool|null $branches_exists
 * @property-read mixed $route_name
 * @property-read \App\Models\Section|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Section> $subSections
 * @property-read int|null $sub_sections_count
 * @property-read bool|null $sub_sections_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section mainClientSideSections()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section mainCompanyAdminSections()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section mainSections()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section mainSuperAdminSections()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereSectionSide($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereSubOf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereTrash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Section withoutTrashed()
 * @mixin \Eloquent
 */
class Section extends Model
{
     use SoftDeletes,StaticBoot;
    protected $guarded = [];
    protected $casts = ['name' => 'array'];

    // protected $with = [
    //     'subSections'
    // ];

    // protected static function booted()
    // {
    //     static::addGlobalScope('sub_of', function (Builder $builder) {
    //         $builder->where('sub_of',0);
    //     });
    // }
    public function scopeMainSections($query)
    {
        return $query->where('sub_of',0);
    }

  
    public function getRouteNameAttribute()
    {
        $route = $this->route;
        $route_array = explode('.',$route);
        $route = $route_array[0];
        return $route;
    }
    public function scopeMainClientSideSections($query)
    {
        return $query->where('sub_of',0)->where('section_side','client')->where('trash',0);
    }
    public function scopeMainSuperAdminSections($query)
    {
        return $query->where('sub_of',0)->where('section_side','admin')->where('trash',0);
    }
	public function scopeMainCompanyAdminSections($query)
    {
        return $query->where('sub_of',0)->where('section_side','company-admin')->where('trash',0);
    }
    public function parent():BelongsTo
    {
        return $this->belongsTo(Section::class, 'sub_of', 'id');
    }
    public function subSections():HasMany
    {
        return $this->hasMany(Section::class, 'sub_of', 'id')->where('trash',0);
    }
    public function branches():BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branches_sections');
    }
	public function isExportable(array $exportables)
	{
		if(str_contains($this->route,'.products.') && in_array('product_or_service',array_keys($exportables))){
			return true ;
		}if(str_contains($this->route,'.Items.') && in_array('product_item',array_keys($exportables))){
			return true ;
		}
		return false;
	}
}
