using AltF4DeviceService.Domain.Entities;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace AltF4DeviceService.Infrastructure.Persistence.Configurations;

public class BranchAccountConfiguration : IEntityTypeConfiguration<BranchAccount>
{
    public void Configure(EntityTypeBuilder<BranchAccount> builder)
    {
        builder.ToTable("BranchAccounts");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.BranchId)
            .IsRequired();

        builder.Property(x => x.RestaurantId)
            .IsRequired();

        builder.Property(x => x.BranchName)
            .IsRequired()
            .HasMaxLength(150);

        builder.Property(x => x.Email)
            .HasMaxLength(150);

        builder.Property(x => x.DeviceToken)
            .HasMaxLength(256);

        builder.Property(x => x.Status)
            .HasConversion<string>()
            .IsRequired()
            .HasMaxLength(32);

        builder.Property(x => x.CreatedAt)
            .IsRequired();
    }
}
